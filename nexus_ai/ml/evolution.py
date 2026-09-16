"""Controlled dataset and model evolution for Nexus."""

import json
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from nexus_ai.datasets.schema import validate_record_shape

from .pipeline import NexusMlPipeline, TrainingReport


@dataclass(frozen=True)
class LearningExample:
    example_id: str
    input: str
    target: dict[str, Any]
    outcome: str
    confidence: float | None = None
    requires_clarification: bool = False
    correction: str | None = None
    validated: bool = False

    def validate(self) -> list[str]:
        errors = []
        if not self.example_id.strip() or not self.input.strip():
            errors.append("example_id and input are required")
        if self.outcome not in {"correct", "failed", "low_confidence", "needs_clarification", "corrected"}:
            errors.append("unsupported outcome")
        if self.confidence is not None and not 0 <= self.confidence <= 1:
            errors.append("confidence must be between 0 and 1")
        if self.outcome == "corrected" and not self.correction:
            errors.append("corrected examples require correction")
        return errors

    def to_record(self, split: str = "train") -> dict[str, Any]:
        record = {
            "id": self.example_id,
            "version": "1.0.0",
            "split": split,
            "input": self.input,
            "target": self.target,
        }
        return record


@dataclass(frozen=True)
class ModelComparison:
    baseline_version: str
    candidate_version: str
    baseline_f1: float
    candidate_f1: float
    regression: bool
    approved: bool = False
    reason: str = ""


class EvolutionRegistry:
    """Stores history and requires explicit approval before activation."""

    def __init__(self, path: str | Path):
        self.path = Path(path)
        self.path.parent.mkdir(parents=True, exist_ok=True)
        self.history = self._load()

    def _load(self) -> list[dict[str, Any]]:
        if not self.path.exists():
            return []
        return json.loads(self.path.read_text(encoding="utf-8"))

    def record(self, report: TrainingReport) -> None:
        self.history.append({"recorded_at": datetime.now(timezone.utc).isoformat(), **report.to_dict()})
        self.path.write_text(json.dumps(self.history, ensure_ascii=False, indent=2), encoding="utf-8")

    def compare(self, baseline: TrainingReport, candidate: TrainingReport, target: str = "intent") -> ModelComparison:
        baseline_f1 = baseline.metrics[target]["f1_macro"]
        candidate_f1 = candidate.metrics[target]["f1_macro"]
        regression = candidate_f1 < baseline_f1
        return ModelComparison(
            baseline.model_version,
            candidate.model_version,
            baseline_f1,
            candidate_f1,
            regression,
            False,
            "regression detected" if regression else "awaiting explicit approval",
        )

    def activate(self, comparison: ModelComparison, *, approved: bool = False) -> ModelComparison:
        if comparison.regression:
            raise ValueError("candidate model has a regression")
        if not approved:
            raise PermissionError("explicit approval is required before activation")
        return ModelComparison(
            comparison.baseline_version, comparison.candidate_version,
            comparison.baseline_f1, comparison.candidate_f1,
            comparison.regression, True, "explicitly approved",
        )


def validate_learning_example(example: LearningExample) -> LearningExample:
    errors = example.validate()
    if errors:
        raise ValueError("; ".join(errors))
    record_errors = validate_record_shape(example.to_record())
    if record_errors:
        raise ValueError("; ".join(record_errors))
    if not example.validated:
        raise PermissionError("example requires explicit validation before dataset inclusion")
    return example


def append_validated_examples(path: str | Path, examples: list[LearningExample]) -> int:
    records = [validate_learning_example(example).to_record() for example in examples]
    destination = Path(path)
    destination.parent.mkdir(parents=True, exist_ok=True)
    with destination.open("a", encoding="utf-8") as handle:
        for record in records:
            handle.write(json.dumps(record, ensure_ascii=False) + "\n")
    return len(records)
