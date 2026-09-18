from __future__ import annotations

import hashlib
import hmac
import json
import math
import os
import platform
import random
import time
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable


@dataclass(frozen=True)
class TrainingConfig:
    seed: int = 1337
    hidden_size: int = 32
    optimizer: str = "sgd"
    learning_rate: float = 0.05
    scheduler: str = "constant"
    scheduler_gamma: float = 0.95
    min_learning_rate: float = 0.0
    epochs: int = 3
    batch_size: int = 1
    gradient_accumulation_steps: int = 1
    gradient_clip_norm: float | None = 1.0
    eval_interval: int = 10
    validation_interval: int | None = None
    checkpoint_interval: int = 25
    early_stopping_patience: int | None = None
    max_samples: int | None = None
    model_version: str = "nexus-micro-v1"
    tokenizer_version: str = "unknown"
    architecture: str = "one-token-causal-embedding"
    baseline_checkpoint: str | None = None

    def validate(self) -> None:
        if self.epochs < 1 or self.learning_rate <= 0:
            raise ValueError("epochs must be positive and learning_rate must be positive")
        if self.optimizer not in {"sgd", "adam"}:
            raise ValueError("optimizer must be 'sgd' or 'adam'")
        if self.scheduler not in {"constant", "cosine", "exponential"}:
            raise ValueError("scheduler must be constant, cosine, or exponential")
        if self.batch_size < 1 or self.gradient_accumulation_steps < 1:
            raise ValueError("batch_size and gradient_accumulation_steps must be positive")
        if self.eval_interval < 1 or self.checkpoint_interval < 1:
            raise ValueError("evaluation and checkpoint intervals must be positive")
        if self.validation_interval is not None and self.validation_interval < 1:
            raise ValueError("validation_interval must be positive")
        if self.gradient_clip_norm is not None and self.gradient_clip_norm <= 0:
            raise ValueError("gradient_clip_norm must be positive when set")
        if self.early_stopping_patience is not None and self.early_stopping_patience < 1:
            raise ValueError("early_stopping_patience must be positive when set")

    @property
    def effective_validation_interval(self) -> int:
        return self.validation_interval or self.eval_interval


class NexusMicroModel:
    """Dependency-free causal model used by the reproducible training contract."""

    def __init__(self, vocab_size: int, hidden_size: int, seed: int = 1337) -> None:
        if vocab_size < 2 or hidden_size < 1:
            raise ValueError("vocab_size must be >= 2 and hidden_size must be positive")
        self.vocab_size = vocab_size
        self.hidden_size = hidden_size
        generator = random.Random(seed)
        scale = 1 / math.sqrt(hidden_size)
        self.embeddings = [[generator.uniform(-scale, scale) for _ in range(hidden_size)]
                           for _ in range(vocab_size)]
        self.output = [[generator.uniform(-scale, scale) for _ in range(hidden_size)]
                       for _ in range(vocab_size)]

    def logits(self, token_id: int) -> list[float]:
        context = self.embeddings[token_id % self.vocab_size]
        return [sum(weight * value for weight, value in zip(row, context))
                for row in self.output]

    @staticmethod
    def _log_softmax(values: list[float]) -> tuple[list[float], float]:
        maximum = max(values)
        exponentials = [math.exp(value - maximum) for value in values]
        log_total = maximum + math.log(sum(exponentials))
        return [value - log_total for value in values], log_total

    def train_pair(self, source: int, target: int, learning_rate: float,
                   gradient_clip_norm: float | None = None) -> float:
        source %= self.vocab_size
        target %= self.vocab_size
        context = self.embeddings[source][:]
        log_probs, _ = self._log_softmax(self.logits(source))
        probabilities = [math.exp(value) for value in log_probs]
        loss = -log_probs[target]
        context_gradient = [0.0] * self.hidden_size
        for index, probability in enumerate(probabilities):
            gradient = probability - (1.0 if index == target else 0.0)
            for dimension in range(self.hidden_size):
                context_gradient[dimension] += gradient * self.output[index][dimension]
                self.output[index][dimension] -= learning_rate * gradient * context[dimension]
        norm = math.sqrt(sum(value * value for value in context_gradient))
        scale = min(1.0, gradient_clip_norm / norm) if gradient_clip_norm and norm else 1.0
        for dimension in range(self.hidden_size):
            self.embeddings[source][dimension] -= learning_rate * context_gradient[dimension] * scale
        return loss

    def generate(self, prefix: Iterable[int], length: int = 16) -> list[int]:
        result = list(prefix) or [0]
        for _ in range(length):
            scores = self.logits(result[-1])
            result.append(max(range(self.vocab_size), key=scores.__getitem__))
        return result

    def to_dict(self) -> dict:
        return {"format": "nexus-micro-causal-v1", "vocab_size": self.vocab_size,
                "hidden_size": self.hidden_size, "embeddings": self.embeddings,
                "output": self.output}

    @classmethod
    def from_dict(cls, value: dict) -> "NexusMicroModel":
        model = cls(value["vocab_size"], value["hidden_size"], seed=0)
        model.embeddings, model.output = value["embeddings"], value["output"]
        return model


def _read_examples(dataset_path: str | Path, split: str) -> list[list[int]]:
    examples: list[list[int]] = []
    for path in sorted(Path(dataset_path).glob(f"{split}-*.jsonl")):
        for line in path.read_text(encoding="utf-8").splitlines():
            if line.strip():
                ids = json.loads(line).get("input_ids")
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
            log_probs, _ = model._log_softmax(model.logits(source))
            losses.append(-log_probs[target % model.vocab_size])
    return sum(losses) / len(losses) if losses else None


def _token_count(examples: list[list[int]]) -> int:
    return sum(len(sequence) for sequence in examples)


def _perplexity(loss: float | None) -> float | None:
    return math.exp(min(loss, 50)) if loss is not None else None


def _atomic_write(path: Path, payload: dict) -> None:
    temporary = path.with_suffix(path.suffix + ".tmp")
    temporary.write_text(json.dumps(payload, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")
    temporary.replace(path)


def _checkpoint_payload(model: NexusMicroModel, step: int, epoch: int, config: TrainingConfig,
                        dataset_hash: str, training_loss: float | None,
                        validation_loss: float | None, optimizer_step: int) -> dict:
    return {
        "format": "nexus-micro-checkpoint-v2",
        "model_version": config.model_version,
        "dataset_version": dataset_hash,
        "dataset_sha256": dataset_hash,
        "tokenizer_version": config.tokenizer_version,
        "architecture": config.architecture,
        "training_configuration": asdict(config),
        "config": asdict(config),
        "epoch": epoch,
        "step": step,
        "optimizer_step": optimizer_step,
        "training_loss": training_loss,
        "validation_loss": validation_loss,
        "model": model.to_dict(),
    }


def _write_checkpoint(directory: Path, model: NexusMicroModel, step: int, epoch: int,
                      config: TrainingConfig, dataset_hash: str,
                      training_loss: float | None, validation_loss: float | None,
                      optimizer_step: int) -> Path:
    directory.mkdir(parents=True, exist_ok=True)
    payload = _checkpoint_payload(model, step, epoch, config, dataset_hash,
                                  training_loss, validation_loss, optimizer_step)
    path = directory / f"checkpoint-{step:08d}.json"
    _atomic_write(path, payload)
    _atomic_write(directory / "latest.json", payload)
    return path


def _load_checkpoint(path: str | Path) -> tuple[NexusMicroModel, dict]:
    payload = json.loads(Path(path).read_text(encoding="utf-8"))
    if payload.get("format") not in {"nexus-micro-checkpoint-v1", "nexus-micro-checkpoint-v2"}:
        raise ValueError("unsupported checkpoint format")
    return NexusMicroModel.from_dict(payload["model"]), payload


def load_checkpoint(path: str | Path) -> NexusMicroModel:
    return _load_checkpoint(path)[0]


def _learning_rate(config: TrainingConfig, step: int, total_steps: int) -> float:
    if config.scheduler == "cosine":
        factor = (1 + math.cos(math.pi * min(step, total_steps) / max(total_steps, 1))) / 2
    elif config.scheduler == "exponential":
        factor = config.scheduler_gamma ** step
    else:
        factor = 1.0
    return max(config.min_learning_rate, config.learning_rate * factor)


def _compare_baseline(summary: dict, baseline_path: str | None,
                      baseline_summary: dict | None = None) -> dict | None:
    if baseline_summary is None and not baseline_path:
        return None
    if baseline_summary is not None:
        baseline = baseline_summary
    else:
        _, payload = _load_checkpoint(baseline_path)
        baseline = payload.get("metrics") or payload
    current = {"validation_loss": summary["final_validation_loss"],
               "test_loss": summary["final_test_loss"],
               "perplexity": summary["test_perplexity"]}
    return {"baseline_checkpoint": str(baseline_path),
            "baseline": {key: baseline.get(key) for key in current},
            "current": current,
            "lower_is_better": ["validation_loss", "test_loss", "perplexity"]}


def train(dataset_path: str | Path, output_path: str | Path, vocab_size: int,
          config: TrainingConfig | None = None, resume: str | Path | None = None,
          *, require_approval: bool = True) -> dict:
    config = config or TrainingConfig()
    config.validate()
    dataset = Path(dataset_path)
    approval = dataset / "approval.json"
    if require_approval and not approval.is_file():
        raise ValueError("training requires an explicitly approved dataset (approval.json)")
    train_examples = _read_examples(dataset, "training")
    validation_examples = _read_examples(dataset, "validation")
    test_examples = _read_examples(dataset, "test")
    if not train_examples:
        raise ValueError("dataset has no training examples")
    if config.max_samples is not None:
        train_examples = train_examples[:config.max_samples]
    dataset_hash = _dataset_hash(dataset)
    if require_approval:
        try:
            approval_value = json.loads(approval.read_text(encoding="utf-8"))
        except (OSError, ValueError) as error:
            raise ValueError("dataset approval marker is invalid") from error
        if (approval_value.get("format") != "nexus-dataset-approval-v1"
                or approval_value.get("dataset_sha256") != dataset_hash
                or not approval_value.get("approved_by")
                or not approval_value.get("signature")
                or not _valid_approval_signature(approval_value, dataset_hash)):
            raise ValueError("dataset approval does not match the exact approved dataset")
    output = Path(output_path)
    output.mkdir(parents=True, exist_ok=True)
    previous_summary = None
    previous_summary_path = output / "summary.json"
    if previous_summary_path.is_file() and not resume:
        try:
            previous_summary = json.loads(previous_summary_path.read_text(encoding="utf-8"))
        except (OSError, ValueError):
            previous_summary = None
    started = time.perf_counter()
    if resume:
        model, checkpoint = _load_checkpoint(resume)
        if checkpoint.get("dataset_version", checkpoint.get("dataset_sha256")) != dataset_hash:
            raise ValueError("checkpoint dataset does not match current dataset")
        step, start_epoch = int(checkpoint["step"]), int(checkpoint["epoch"])
        optimizer_step = int(checkpoint.get("optimizer_step", step))
        best_validation = checkpoint.get("best_validation_loss", checkpoint.get("validation_loss"))
    else:
        model, step, start_epoch, optimizer_step, best_validation = (
            NexusMicroModel(vocab_size, config.hidden_size, config.seed), 0, 0, 0, None
        )
    log_path = output / "training-log.jsonl"
    rng = random.Random(config.seed + start_epoch)
    latest_loss = None
    latest_validation = None
    bad_evaluations = 0
    interrupted = False
    total_steps = max(config.epochs * math.ceil(len(train_examples) / config.batch_size), 1)
    log_mode = "a" if resume else "w"
    with log_path.open(log_mode, encoding="utf-8", newline="\n") as log:
        try:
            for epoch in range(start_epoch, config.epochs):
                order = list(range(len(train_examples)))
                rng.shuffle(order)
                for batch_start in range(0, len(order), config.batch_size):
                    batch = order[batch_start:batch_start + config.batch_size]
                    losses = []
                    for index in batch:
                        sequence = train_examples[index]
                        losses.extend(model.train_pair(source, target,
                            _learning_rate(config, optimizer_step, total_steps),
                            config.gradient_clip_norm)
                            for source, target in zip(sequence, sequence[1:]))
                    latest_loss = sum(losses) / len(losses)
                    step += 1
                    if step % config.gradient_accumulation_steps == 0:
                        optimizer_step += 1
                    validation_due = step % config.effective_validation_interval == 0
                    if validation_due:
                        latest_validation = _evaluate(model, validation_examples)
                        if latest_validation is not None and (
                                best_validation is None or latest_validation < best_validation):
                            best_validation, bad_evaluations = latest_validation, 0
                            payload = _checkpoint_payload(model, step, epoch + 1, config,
                                dataset_hash, latest_loss, latest_validation, optimizer_step)
                            payload["best_validation_loss"] = best_validation
                            _atomic_write(output / "best.json", payload)
                        else:
                            bad_evaluations += 1
                    event = {
                        "event": "evaluation" if validation_due else "step",
                        "step": step, "epoch": epoch + 1, "training_loss": latest_loss,
                        "loss": latest_loss, "validation_loss": latest_validation,
                        "learning_rate": _learning_rate(config, optimizer_step, total_steps),
                        "optimizer": config.optimizer, "scheduler": config.scheduler,
                        "elapsed_seconds": time.perf_counter() - started,
                        "parameters": model.vocab_size * model.hidden_size * 2,
                    }
                    log.write(json.dumps(event, ensure_ascii=True) + "\n")
                    log.flush()
                    if step % config.checkpoint_interval == 0:
                        _write_checkpoint(output, model, step, epoch + 1, config, dataset_hash,
                                          latest_loss, latest_validation, optimizer_step)
                    if (config.early_stopping_patience is not None
                            and bad_evaluations >= config.early_stopping_patience):
                        break
                _write_checkpoint(output, model, step, epoch + 1, config, dataset_hash,
                                  latest_loss, latest_validation, optimizer_step)
                if (config.early_stopping_patience is not None
                        and bad_evaluations >= config.early_stopping_patience):
                    break
        except KeyboardInterrupt:
            interrupted = True
            _write_checkpoint(output, model, step, epoch + 1, config, dataset_hash,
                              latest_loss, latest_validation, optimizer_step)
    inference_started = time.perf_counter()
    final_validation = _evaluate(model, validation_examples)
    final_test = _evaluate(model, test_examples)
    inference_seconds = time.perf_counter() - inference_started
    checkpoint_path = output / "latest.json"
    summary = {
        "format": "nexus-training-summary-v2", "status": "interrupted" if interrupted else "completed",
        "model_version": config.model_version, "dataset_version": dataset_hash,
        "tokenizer_version": config.tokenizer_version, "architecture": config.architecture,
        "dataset_sha256": dataset_hash, "steps": step, "epochs": config.epochs,
        "tokens_processed": _token_count(train_examples) * max(start_epoch + 1, 1),
        "dataset_tokens": {"training": _token_count(train_examples),
                           "validation": _token_count(validation_examples),
                           "test": _token_count(test_examples),
                           "total": _token_count(train_examples + validation_examples + test_examples)},
        "final_training_loss": latest_loss, "final_validation_loss": final_validation,
        "final_test_loss": final_test, "validation_perplexity": _perplexity(final_validation),
        "test_perplexity": _perplexity(final_test), "parameters": model.vocab_size * model.hidden_size * 2,
        "checkpoint": str(checkpoint_path), "checkpoint_bytes": checkpoint_path.stat().st_size
        if checkpoint_path.exists() else 0, "training_seconds": time.perf_counter() - started,
        "inference_seconds": inference_seconds, "baseline_comparison": None,
    }
    summary["baseline_comparison"] = _compare_baseline(
        summary, config.baseline_checkpoint, previous_summary)
    _atomic_write(output / "summary.json", summary)
    return summary


def _valid_approval_signature(approval: dict, dataset_hash: str) -> bool:
    key = os.environ.get("NEXUS_DATASET_APPROVAL_KEY")
    if not key:
        return False
    message = f"{dataset_hash}:{approval['approved_by']}"
    expected = hmac.new(key.encode(), message.encode(), hashlib.sha256).hexdigest()
    return hmac.compare_digest(str(approval.get("signature")), expected)
