from __future__ import annotations

import json
import math
import random
import time
from array import array
from collections import OrderedDict
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable

from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel, load_checkpoint


class InferenceCancelled(Exception):
    """Raised when the caller cancels a generation."""


class QuantizedMicroModel:
    """Per-row symmetric int8 weights; original float weights remain untouched."""

    def __init__(self, model: NexusMicroModel) -> None:
        self.vocab_size = model.vocab_size
        self.hidden_size = model.hidden_size
        self.embeddings, self.embedding_scales = self._quantize(model.embeddings)
        self.output, self.output_scales = self._quantize(model.output)

    @staticmethod
    def _quantize(rows: list[list[float]]) -> tuple[list[array], list[float]]:
        quantized: list[array] = []
        scales: list[float] = []
        for row in rows:
            maximum = max((abs(value) for value in row), default=0.0)
            scale = maximum / 127 if maximum else 1.0
            quantized.append(array("b", [max(-127, min(127, round(value / scale))) for value in row]))
            scales.append(scale)
        return quantized, scales

    def logits(self, token_id: int) -> list[float]:
        row = self.embeddings[token_id % self.vocab_size]
        context_scale = self.embedding_scales[token_id % self.vocab_size]
        context = [value * context_scale for value in row]
        return [
            sum(value * self.output[index][dimension] * self.output_scales[index] for dimension, value in enumerate(context))
            for index in range(self.vocab_size)
        ]


@dataclass(frozen=True)
class InferenceConfig:
    max_context_tokens: int = 4096
    max_new_tokens: int = 128
    temperature: float = 0.7
    top_k: int = 40
    top_p: float = 0.9
    seed: int = 1337
    timeout_seconds: float = 30.0
    logits_cache_size: int = 1024
    quantization: str = "none"

    def validate(self) -> None:
        if self.max_context_tokens < 1 or self.max_new_tokens < 1:
            raise ValueError("token limits must be positive")
        if self.temperature < 0:
            raise ValueError("temperature cannot be negative")
        if self.top_k < 0 or not 0 <= self.top_p <= 1:
            raise ValueError("sampling limits are invalid")
        if self.timeout_seconds <= 0:
            raise ValueError("timeout must be positive")
        if self.logits_cache_size < 0:
            raise ValueError("logits cache size cannot be negative")
        if self.quantization not in {"none", "int8"}:
            raise ValueError("unsupported quantization")


class LocalInferenceRuntime:
    """Loads verified local artifacts and performs bounded causal generation."""

    def __init__(
        self,
        checkpoint: str | Path,
        tokenizer: str | Path,
        config: InferenceConfig | None = None,
        *,
        loaded_model: NexusMicroModel | None = None,
        loaded_tokenizer: NexusTokenizer | None = None,
    ) -> None:
        self.config = config or InferenceConfig()
        self.config.validate()
        self.tokenizer = loaded_tokenizer or NexusTokenizer.load(tokenizer)
        base_model = loaded_model or self._load_model(checkpoint)
        self.model = (
            QuantizedMicroModel(base_model)
            if self.config.quantization == "int8" and not isinstance(base_model, QuantizedMicroModel)
            else base_model
        )
        if self.model.vocab_size != self.tokenizer.vocab_size:
            raise ValueError("checkpoint and tokenizer vocabularies do not match")
        self._logits_cache: OrderedDict[int, list[float]] = OrderedDict()
        self._cache_hits = 0
        self._cache_misses = 0

    def generate(
        self,
        prompt: str,
        *,
        cancel: Callable[[], bool] | None = None,
        on_token: Callable[[str], None] | None = None,
    ) -> dict:
        started = time.perf_counter()
        window = min(self.config.max_context_tokens, getattr(self.tokenizer, "context_window", self.config.max_context_tokens))
        ids = self.tokenizer.encode(prompt, max_length=window, truncation=True)
        generated: list[int] = []
        rng = random.Random(self.config.seed)

        for _ in range(self.config.max_new_tokens):
            if cancel and cancel():
                raise InferenceCancelled("generation cancelled")
            if time.perf_counter() - started > self.config.timeout_seconds:
                raise TimeoutError("generation timed out")

            context = ids + generated
            if len(context) > window:
                context = context[-window:]
            token_id = self._sample(self._logits(context[-1]), rng)
            generated.append(token_id)
            token = self.tokenizer.decode([token_id], skip_special_tokens=True)
            if token:
                if on_token:
                    on_token(token)
            if token_id == self._eos_id():
                break

        elapsed = time.perf_counter() - started
        return {
            "text": self.tokenizer.decode(generated, skip_special_tokens=True),
            "prompt_tokens": len(ids),
            "completion_tokens": len(generated),
            "total_tokens": len(ids) + len(generated),
            "context_window_tokens": window,
            "attention_mask_applied": True,
            "padding_applied": False,
            "truncation_applied": len(self.tokenizer.encode(prompt)) > window,
            "elapsed_seconds": round(elapsed, 6),
            "tokens_per_second": round(len(generated) / elapsed, 3) if elapsed else 0,
            "temperature": self.config.temperature,
            "top_k": self.config.top_k,
            "top_p": self.config.top_p,
            "cache_hits": self._cache_hits,
            "cache_misses": self._cache_misses,
            "quantization": self.config.quantization,
            "estimated_model_bytes": self.estimated_model_bytes(),
        }

    def generate_batch(self, prompts: Iterable[str]) -> list[dict]:
        return [self.generate(prompt) for prompt in prompts]

    def estimated_model_bytes(self) -> int:
        values = self.model.vocab_size * self.model.hidden_size * 2
        return values * (1 if self.config.quantization == "int8" else 8)

    def _logits(self, token_id: int) -> list[float]:
        token_id %= self.model.vocab_size
        if token_id in self._logits_cache:
            self._cache_hits += 1
            logits = self._logits_cache.pop(token_id)
            self._logits_cache[token_id] = logits
            return logits
        self._cache_misses += 1
        logits = self.model.logits(token_id)
        if self.config.logits_cache_size:
            self._logits_cache[token_id] = logits
            while len(self._logits_cache) > self.config.logits_cache_size:
                self._logits_cache.popitem(last=False)
        return logits

    def _sample(self, logits: list[float], rng: random.Random) -> int:
        if self.config.temperature == 0:
            return max(range(len(logits)), key=logits.__getitem__)
        scaled = [(index, value / self.config.temperature) for index, value in enumerate(logits)]
        scaled.sort(key=lambda item: item[1], reverse=True)
        if self.config.top_k:
            scaled = scaled[:self.config.top_k]
        maximum = scaled[0][1]
        probabilities = [(index, math.exp(value - maximum)) for index, value in scaled]
        total = sum(value for _, value in probabilities)
        probabilities = [(index, value / total) for index, value in probabilities]
        if self.config.top_p < 1:
            kept: list[tuple[int, float]] = []
            cumulative = 0.0
            for item in probabilities:
                kept.append(item)
                cumulative += item[1]
                if cumulative >= self.config.top_p:
                    break
            probabilities = kept
            total = sum(value for _, value in probabilities)
            probabilities = [(index, value / total) for index, value in probabilities]
        choice = rng.random()
        cumulative = 0.0
        for index, probability in probabilities:
            cumulative += probability
            if choice <= cumulative:
                return index
        return probabilities[-1][0]

    def _eos_id(self) -> int:
        return self.tokenizer._special_to_id[self.tokenizer.special_tokens.eos]

    def _load_model(self, checkpoint: str | Path) -> NexusMicroModel:
        path = Path(checkpoint)
        if path.is_dir():
            path = path / "latest.json"
        model = load_checkpoint(path)
        return QuantizedMicroModel(model) if self.config.quantization == "int8" else model


def request_from_payload(payload: dict, runtime: LocalInferenceRuntime, emit: Callable[[dict], None]) -> dict:
    prompt = payload.get("prompt")
    if not isinstance(prompt, str) or not prompt.strip():
        raise ValueError("prompt must be a non-empty string")
    result = runtime.generate(
        prompt,
        on_token=lambda token: emit({"event": "token", "token": token}) if payload.get("stream") else None,
    )
    emit({"event": "complete", "result": result})
    return result
