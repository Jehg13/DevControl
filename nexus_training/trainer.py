from __future__ import annotations

import hashlib
import json
import math
import platform
import random
import time
import tracemalloc
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable


@dataclass(frozen=True)
class TrainingConfig:
    seed: int = 1337
    hidden_size: int = 32
    learning_rate: float = 0.05
    epochs: int = 3
    batch_size: int = 1
    eval_interval: int = 10
    checkpoint_interval: int = 25
    max_samples: int | None = None


class NexusMicroModel:
    """Small causal language model used to validate the training contract.

    This is intentionally dependency-free and uses a one-token causal
    embedding context. Its state and checkpoint format are compatible with a
    future decoder-only Transformer adapter, but it is not the final 2.3B
    architecture.
    """

    def __init__(self, vocab_size: int, hidden_size: int, seed: int = 1337) -> None:
        if vocab_size < 2 or hidden_size < 1:
            raise ValueError("vocab_size must be >= 2 and hidden_size must be positive")
        self.vocab_size = vocab_size
        self.hidden_size = hidden_size
        generator = random.Random(seed)
        scale = 1 / math.sqrt(hidden_size)
        self.embeddings = [
            [generator.uniform(-scale, scale) for _ in range(hidden_size)]
            for _ in range(vocab_size)
        ]
        self.output = [
            [generator.uniform(-scale, scale) for _ in range(hidden_size)]
            for _ in range(vocab_size)
        ]

    def logits(self, token_id: int) -> list[float]:
        context = self.embeddings[token_id % self.vocab_size]
        return [
            sum(weight * value for weight, value in zip(row, context))
            for row in self.output
        ]

    @staticmethod
    def _log_softmax(values: list[float]) -> tuple[list[float], float]:
        maximum = max(values)
        exponentials = [math.exp(value - maximum) for value in values]
        total = sum(exponentials)
        log_total = maximum + math.log(total)
        return [value - log_total for value in values], log_total

    def train_pair(self, source: int, target: int, learning_rate: float) -> float:
        source %= self.vocab_size
        target %= self.vocab_size
        context = self.embeddings[source][:]
        logits = self.logits(source)
        log_probs, _ = self._log_softmax(logits)
        probabilities = [math.exp(value) for value in log_probs]
        loss = -log_probs[target]
        context_gradient = [0.0] * self.hidden_size
        for index, probability in enumerate(probabilities):
            gradient = probability - (1.0 if index == target else 0.0)
            for dimension in range(self.hidden_size):
                context_gradient[dimension] += gradient * self.output[index][dimension]
                self.output[index][dimension] -= learning_rate * gradient * context[dimension]
        for dimension in range(self.hidden_size):
            self.embeddings[source][dimension] -= learning_rate * context_gradient[dimension]
        return loss

    def generate(self, prefix: Iterable[int], length: int = 16) -> list[int]:
        result = list(prefix)
        if not result:
            result = [0]
        for _ in range(length):
            scores = self.logits(result[-1])
            result.append(max(range(self.vocab_size), key=scores.__getitem__))
        return result

    def to_dict(self) -> dict:
        return {
            "format": "nexus-micro-causal-v1",
            "vocab_size": self.vocab_size,
            "hidden_size": self.hidden_size,
            "embeddings": self.embeddings,
            "output": self.output,
        }

    @classmethod
    def from_dict(cls, value: dict) -> "NexusMicroModel":
        model = cls(value["vocab_size"], value["hidden_size"], seed=0)
        model.embeddings = value["embeddings"]
        model.output = value["output"]
        return model


def _read_examples(dataset_path: str | Path, split: str) -> list[list[int]]:
    paths = sorted(Path(dataset_path).glob(f"{split}-*.jsonl"))
    examples: list[list[int]] = []
    for path in paths:
        for line in path.read_text(encoding="utf-8").splitlines():
            if line.strip():
                value = json.loads(line)
                ids = value.get("input_ids")
                if isinstance(ids, list) and len(ids) >= 2:
                    examples.append([int(token) for token in ids])
    return examples


def _dataset_hash(dataset_path: str | Path) -> str:
    digest = hashlib.sha256()
    for path in sorted(Path(dataset_path).glob("*.jsonl")):
        digest.update(path.name.encode())
        digest.update(path.read_bytes())
    return digest.hexdigest()


def _evaluate(model: NexusMicroModel, examples: list[list[int]]) -> float | None:
    losses = []
    for sequence in examples:
        for source, target in zip(sequence, sequence[1:]):
            logits = model.logits(source)
            log_probs, _ = model._log_softmax(logits)
            losses.append(-log_probs[target % model.vocab_size])
    return sum(losses) / len(losses) if losses else None


def _write_checkpoint(
    directory: Path,
    model: NexusMicroModel,
    step: int,
    epoch: int,
    config: TrainingConfig,
    dataset_hash: str,
) -> Path:
    directory.mkdir(parents=True, exist_ok=True)
    payload = {
        "format": "nexus-micro-checkpoint-v1",
        "step": step,
        "epoch": epoch,
        "config": asdict(config),
        "dataset_sha256": dataset_hash,
        "model": model.to_dict(),
    }
    path = directory / f"checkpoint-{step:08d}.json"
    path.write_text(json.dumps(payload, ensure_ascii=True), encoding="utf-8")
    (directory / "latest.json").write_text(json.dumps(payload, ensure_ascii=True), encoding="utf-8")
    return path


def _load_checkpoint(path: str | Path) -> tuple[NexusMicroModel, int, int, str]:
    payload = json.loads(Path(path).read_text(encoding="utf-8"))
    if payload.get("format") != "nexus-micro-checkpoint-v1":
        raise ValueError("unsupported checkpoint format")
    model = NexusMicroModel.from_dict(payload["model"])
    return model, int(payload["step"]), int(payload["epoch"]), payload["dataset_sha256"]


def load_checkpoint(path: str | Path) -> NexusMicroModel:
    """Load model weights for local inference and benchmark evaluation."""
    return _load_checkpoint(path)[0]


def train(
    dataset_path: str | Path,
    output_path: str | Path,
    vocab_size: int,
    config: TrainingConfig | None = None,
    resume: str | Path | None = None,
    *,
    require_approval: bool = False,
) -> dict:
    config = config or TrainingConfig()
    if config.epochs < 1 or config.learning_rate <= 0:
        raise ValueError("epochs must be positive and learning_rate must be positive")
    if require_approval:
        approval = Path(dataset_path) / "approval.json"
        if not approval.is_file():
            raise ValueError("training requires an explicitly approved dataset (approval.json)")
        try:
            approval_value = json.loads(approval.read_text(encoding="utf-8"))
        except (OSError, ValueError) as error:
            raise ValueError("dataset approval marker is invalid") from error
        if approval_value.get("format") != "nexus-dataset-approval-v1":
            raise ValueError("unsupported dataset approval marker")
    train_examples = _read_examples(dataset_path, "training")
    validation_examples = _read_examples(dataset_path, "validation")
    if not train_examples:
        raise ValueError("dataset has no training examples")
    if config.max_samples is not None:
        train_examples = train_examples[:config.max_samples]
    dataset_hash = _dataset_hash(dataset_path)
    output = Path(output_path)
    output.mkdir(parents=True, exist_ok=True)
    tracemalloc.start()
    started = time.perf_counter()
    if resume:
        model, step, start_epoch, checkpoint_dataset_hash = _load_checkpoint(resume)
        if checkpoint_dataset_hash != dataset_hash:
            raise ValueError("checkpoint dataset does not match current dataset")
    else:
        model = NexusMicroModel(vocab_size, config.hidden_size, config.seed)
        step, start_epoch = 0, 0
    log_path = output / "training-log.jsonl"
    mode = "a" if resume else "w"
    rng = random.Random(config.seed + start_epoch)
    metrics: list[dict] = []
    with log_path.open(mode, encoding="utf-8", newline="\n") as log:
        for epoch in range(start_epoch, config.epochs):
            order = list(range(len(train_examples)))
            rng.shuffle(order)
            for index in order:
                sequence = train_examples[index]
                losses = [
                    model.train_pair(source, target, config.learning_rate)
                    for source, target in zip(sequence, sequence[1:])
                ]
                step += 1
                event = {
                    "event": "step",
                    "step": step,
                    "epoch": epoch + 1,
                    "loss": sum(losses) / len(losses),
                    "tokens_processed": sum(len(train_examples[item]) for item in order[:index + 1]),
                    "elapsed_seconds": time.perf_counter() - started,
                    "memory_peak_bytes": tracemalloc.get_traced_memory()[1],
                    "cpu_count": __import__("os").cpu_count(),
                    "platform": platform.platform(),
                }
                if step % config.eval_interval == 0:
                    event["validation_loss"] = _evaluate(model, validation_examples)
                    event["event"] = "evaluation"
                log.write(json.dumps(event, ensure_ascii=True) + "\n")
                log.flush()
                metrics.append(event)
                if step % config.checkpoint_interval == 0:
                    _write_checkpoint(output, model, step, epoch + 1, config, dataset_hash)
            _write_checkpoint(output, model, step, epoch + 1, config, dataset_hash)
    tracemalloc.stop()
    summary = {
        "format": "nexus-training-summary-v1",
        "dataset_sha256": dataset_hash,
        "steps": step,
        "epochs": config.epochs,
        "tokens_processed": sum(len(example) for example in train_examples) * config.epochs,
        "final_validation_loss": _evaluate(model, validation_examples),
        "checkpoint": str(output / "latest.json"),
        "training_seconds": time.perf_counter() - started,
    }
    (output / "summary.json").write_text(json.dumps(summary, indent=2) + "\n", encoding="utf-8")
    return summary
