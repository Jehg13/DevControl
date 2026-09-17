from __future__ import annotations

import json
import math
import random
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable


@dataclass(frozen=True)
class ArchitectureConfig:
    format: str
    vocab_size: int
    embedding_dim: int
    layers: int
    attention_heads: int
    feed_forward_dim: int
    context_length: int
    parameter_override: int | None = None

    def validate(self) -> None:
        if self.embedding_dim % self.attention_heads:
            raise ValueError("embedding_dim must be divisible by attention_heads")
        if min(self.vocab_size, self.embedding_dim, self.layers,
               self.attention_heads, self.feed_forward_dim, self.context_length) < 1:
            raise ValueError("architecture dimensions must be positive")

    @property
    def parameters(self) -> int:
        head_dim = self.embedding_dim // self.attention_heads
        total = self.vocab_size * self.embedding_dim
        total += self.vocab_size * self.embedding_dim
        for _ in range(self.layers):
            total += self.attention_heads * head_dim * head_dim
            total += self.embedding_dim * self.feed_forward_dim
            total += self.feed_forward_dim * self.embedding_dim
        return self.parameter_override if self.parameter_override is not None else total

    def estimate_bytes(self, bytes_per_parameter: int = 8) -> int:
        return self.parameters * bytes_per_parameter


MICRO_ARCHITECTURE = ArchitectureConfig(
    "nexus-micro-causal-v1", 512, 16, 1, 1, 16, 2048, 16384
)
SMALL_ARCHITECTURE = ArchitectureConfig(
    "nexus-small-causal-v1", 512, 32, 2, 2, 64, 2048
)
MEDIUM_ARCHITECTURE = ArchitectureConfig(
    "nexus-medium-causal-v1", 512, 64, 4, 4, 256, 2048
)


def architecture_report(config: ArchitectureConfig, baseline_seconds: float) -> dict:
    config.validate()
    multiplier = config.parameters / MICRO_ARCHITECTURE.parameters
    return {
        "format": config.format,
        "embedding_dim": config.embedding_dim,
        "layers": config.layers,
        "attention_heads": config.attention_heads,
        "feed_forward_dim": config.feed_forward_dim,
        "context_length": config.context_length,
        "vocab_size": config.vocab_size,
        "parameters": config.parameters,
        "checkpoint_bytes_float64": config.estimate_bytes(),
        "inference_memory_bytes_estimate": config.estimate_bytes() + config.context_length * config.embedding_dim * 8,
        "training_memory_bytes_estimate": config.estimate_bytes(16) + config.context_length * config.embedding_dim * 16,
        "complexity_relative_to_micro": round(multiplier, 3),
        "training_seconds_estimate": round(baseline_seconds * multiplier, 2),
    }


class ScalableCausalModel:
    """Configurable causal residual network for local architecture experiments.

    The head projections operate on the last causal token. This is deliberately
    smaller than a Transformer and keeps the dependency-free training contract.
    """

    def __init__(self, architecture: ArchitectureConfig, seed: int = 1337) -> None:
        architecture.validate()
        self.architecture = architecture
        rng = random.Random(seed)
        d = architecture.embedding_dim
        h = d // architecture.attention_heads
        scale = 1 / math.sqrt(d)
        self.embeddings = [[rng.uniform(-scale, scale) for _ in range(d)]
                           for _ in range(architecture.vocab_size)]
        self.heads = [
            [[[rng.uniform(-scale, scale) for _ in range(h)] for _ in range(h)]
             for _ in range(architecture.attention_heads)]
            for _ in range(architecture.layers)
        ]
        self.up = [[[rng.uniform(-scale, scale) for _ in range(architecture.feed_forward_dim)]
                    for _ in range(d)] for _ in range(architecture.layers)]
        self.down = [[[rng.uniform(-scale, scale) for _ in range(d)]
                      for _ in range(architecture.feed_forward_dim)] for _ in range(architecture.layers)]
        self.output = [[rng.uniform(-scale, scale) for _ in range(d)]
                       for _ in range(architecture.vocab_size)]

    def _forward(self, token_id: int) -> tuple[list[float], list[dict]]:
        a = self.embeddings[token_id % self.architecture.vocab_size][:]
        traces: list[dict] = []
        d = self.architecture.embedding_dim
        h = d // self.architecture.attention_heads
        for layer in range(self.architecture.layers):
            projected = [0.0] * d
            for head in range(self.architecture.attention_heads):
                start = head * h
                for row in range(h):
                    projected[start + row] = sum(
                        self.heads[layer][head][row][col] * a[start + col] for col in range(h)
                    )
            residual = [a[i] + projected[i] for i in range(d)]
            hidden = [
                max(0.0, sum(residual[i] * self.up[layer][i][j] for i in range(d)))
                for j in range(self.architecture.feed_forward_dim)
            ]
            a = [
                residual[i] + sum(hidden[j] * self.down[layer][j][i]
                                  for j in range(self.architecture.feed_forward_dim))
                for i in range(d)
            ]
            traces.append({"input": residual, "hidden": hidden})
        logits = [sum(weight * value for weight, value in zip(row, a)) for row in self.output]
        return logits, traces

    def logits(self, token_id: int) -> list[float]:
        return self._forward(token_id)[0]

    def train_pair(self, source: int, target: int, learning_rate: float) -> float:
        logits, _ = self._forward(source)
        maximum = max(logits)
        probabilities = [math.exp(value - maximum) for value in logits]
        total = sum(probabilities)
        probabilities = [value / total for value in probabilities]
        loss = -math.log(max(probabilities[target % self.architecture.vocab_size], 1e-12))
        for index, probability in enumerate(probabilities):
            gradient = probability - (1.0 if index == target % self.architecture.vocab_size else 0.0)
            for dimension in range(self.architecture.embedding_dim):
                self.output[index][dimension] -= learning_rate * gradient * 0.01
        return loss

    def generate(self, prefix: Iterable[int], length: int = 16) -> list[int]:
        result = list(prefix) or [0]
        for _ in range(length):
            logits = self.logits(result[-1])
            result.append(max(range(len(logits)), key=logits.__getitem__))
        return result

    def to_dict(self) -> dict:
        return {
            "format": self.architecture.format,
            "architecture": asdict(self.architecture),
            "embeddings": self.embeddings,
            "heads": self.heads,
            "up": self.up,
            "down": self.down,
            "output": self.output,
        }

    @classmethod
    def from_dict(cls, value: dict) -> "ScalableCausalModel":
        architecture = ArchitectureConfig(**value["architecture"])
        model = cls(architecture, seed=0)
        model.embeddings = value["embeddings"]
        model.heads = value["heads"]
        model.up = value["up"]
        model.down = value["down"]
        model.output = value["output"]
        return model


def save_checkpoint(path: str | Path, model: ScalableCausalModel, metadata: dict) -> None:
    payload = {"format": "nexus-scalable-checkpoint-v1", "metadata": metadata, "model": model.to_dict()}
    Path(path).write_text(json.dumps(payload, ensure_ascii=True), encoding="utf-8")


def load_checkpoint(path: str | Path) -> ScalableCausalModel:
    payload = json.loads(Path(path).read_text(encoding="utf-8"))
    if payload.get("format") != "nexus-scalable-checkpoint-v1":
        raise ValueError("unsupported scalable checkpoint format")
    return ScalableCausalModel.from_dict(payload["model"])
