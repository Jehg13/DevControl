from __future__ import annotations

import hashlib
import json
import re
import uuid
from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, Iterable


class Capability:
    INTENT = "intent"
    ENTITIES = "entities"
    REFERENCES = "references"
    GROUNDING = "grounding"
    REASONING = "reasoning"
    DIAGNOSIS = "diagnosis"
    PLANNING = "planning"
    SOLUTIONS = "solutions"
    SECURITY = "security"
    TOOLS = "tools"
    MEMORY = "memory"
    CONSISTENCY = "consistency"

    ALL = (
        INTENT, ENTITIES, REFERENCES, GROUNDING, REASONING, DIAGNOSIS,
        PLANNING, SOLUTIONS, SECURITY, TOOLS, MEMORY, CONSISTENCY,
    )


def _now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def _id(prefix: str) -> str:
    return f"{prefix}-{uuid.uuid4().hex[:12]}"


def _normal(value: Any) -> Any:
    if isinstance(value, str):
        return re.sub(r"\s+", " ", value.strip().casefold())
    if isinstance(value, dict):
        return {key: _normal(value[key]) for key in sorted(value)}
    if isinstance(value, (list, tuple, set)):
        return sorted((_normal(item) for item in value), key=repr)
    return value


@dataclass(frozen=True)
class EvaluationCase:
    case_id: str
    capability: str
    input: dict[str, Any]
    expected: dict[str, Any]
    tags: tuple[str, ...] = ()
    forbidden: dict[str, Any] = field(default_factory=dict)

    def __post_init__(self) -> None:
        if self.capability not in Capability.ALL:
            raise ValueError(f"unsupported capability: {self.capability}")
        if not self.case_id.strip():
            raise ValueError("case_id is required")
        object.__setattr__(self, "tags", tuple(sorted(set(self.tags))))


@dataclass(frozen=True)
class EvaluationDataset:
    version: str
    cases: tuple[EvaluationCase, ...]
    sha256: str
    created_at: str = field(default_factory=_now)
    provenance: dict[str, Any] = field(default_factory=dict)


@dataclass(frozen=True)
class RegressionReport:
    passed: bool
    baseline_run_id: str | None
    regressions: dict[str, float]
    improvements: dict[str, float]
    unchanged: tuple[str, ...]
    blocking: tuple[str, ...]


@dataclass(frozen=True)
class EvaluationRun:
    run_id: str
    dataset_version: str
    metrics: dict[str, float]
    capability_metrics: dict[str, dict[str, float]]
    cases: list[dict[str, Any]]
    regression: RegressionReport
    created_at: str = field(default_factory=_now)


class EvaluationSystem:
    """Versioned, deterministic evaluation with regression gates."""

    def __init__(self, root: str | Path, *, max_regression: float = 0.02) -> None:
        if not 0 <= max_regression <= 1:
            raise ValueError("max_regression must be between 0 and 1")
        self.root = Path(root)
        self.root.mkdir(parents=True, exist_ok=True)
        self.path = self.root / "evaluation-state.json"
        self.max_regression = max_regression
        if not self.path.exists():
            self._save({"schema": "nexus-evaluation-v1", "datasets": {}, "runs": {}, "baseline": None})

    def _load(self) -> dict[str, Any]:
        state = json.loads(self.path.read_text(encoding="utf-8"))
        if state.get("schema") != "nexus-evaluation-v1":
            raise ValueError("unsupported evaluation store schema")
        return state

    def _save(self, state: dict[str, Any]) -> None:
        temporary = self.path.with_suffix(".tmp")
        temporary.write_text(json.dumps(state, ensure_ascii=True, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        temporary.replace(self.path)

    def create_dataset(
        self,
        version: str,
        cases: Iterable[EvaluationCase],
        *,
        provenance: dict[str, Any] | None = None,
    ) -> EvaluationDataset:
        values = tuple(cases)
        if not version.strip() or not values:
            raise ValueError("dataset version and cases are required")
        if len({case.case_id for case in values}) != len(values):
            raise ValueError("evaluation case IDs must be unique")
        payload = json.dumps([asdict(case) for case in values], sort_keys=True, ensure_ascii=True)
        dataset = EvaluationDataset(
            version=version,
            cases=values,
            sha256=hashlib.sha256(payload.encode("utf-8")).hexdigest(),
            provenance=provenance or {},
        )
        state = self._load()
        if version in state["datasets"]:
            raise ValueError(f"dataset version already exists: {version}")
        state["datasets"][version] = asdict(dataset)
        self._save(state)
        return dataset

    def evaluate(
        self,
        dataset_version: str,
        outputs: dict[str, dict[str, Any]],
        *,
        baseline_run_id: str | None = None,
    ) -> EvaluationRun:
        state = self._load()
        raw = state["datasets"].get(dataset_version)
        if raw is None:
            raise KeyError(f"unknown dataset version: {dataset_version}")
        cases = [EvaluationCase(**value) for value in raw["cases"]]
        results = [self._score(case, outputs.get(case.case_id)) for case in cases]
        capability_metrics: dict[str, dict[str, float]] = {}
        for capability in Capability.ALL:
            values = [item["score"] for item in results if item["capability"] == capability]
            if values:
                capability_metrics[capability] = {
                    "score": sum(values) / len(values),
                    "cases": float(len(values)),
                    "passed": float(sum(value >= 1.0 for value in values)),
                }
        metrics = {key: value["score"] for key, value in capability_metrics.items()}
        baseline = baseline_run_id or state.get("baseline")
        regression = self._regression(metrics, state["runs"].get(baseline))
        run = EvaluationRun(_id("evaluation"), dataset_version, metrics, capability_metrics, results, regression)
        state["runs"][run.run_id] = asdict(run)
        self._save(state)
        return run

    def set_baseline(self, run_id: str) -> EvaluationRun:
        state = self._load()
        if run_id not in state["runs"]:
            raise KeyError(f"unknown evaluation run: {run_id}")
        state["baseline"] = run_id
        self._save(state)
        return EvaluationRun(**state["runs"][run_id])

    def get_run(self, run_id: str) -> EvaluationRun:
        value = self._load()["runs"].get(run_id)
        if value is None:
            raise KeyError(f"unknown evaluation run: {run_id}")
        value["regression"] = RegressionReport(**value["regression"])
        return EvaluationRun(**value)

    def _score(self, case: EvaluationCase, actual: dict[str, Any] | None) -> dict[str, Any]:
        if actual is None:
            return {"case_id": case.case_id, "capability": case.capability, "score": 0.0, "missing": True}
        expected = {key: _normal(value) for key, value in case.expected.items()}
        received = {key: _normal(actual.get(key)) for key in case.expected}
        matches = sum(received[key] == expected[key] for key in expected)
        score = matches / len(expected) if expected else 1.0
        violations = [key for key, value in case.forbidden.items() if _normal(actual.get(key)) == _normal(value)]
        if violations:
            score = 0.0
        return {
            "case_id": case.case_id,
            "capability": case.capability,
            "score": round(score, 6),
            "matches": matches,
            "expected_fields": len(expected),
            "violations": violations,
        }

    def _regression(self, metrics: dict[str, float], baseline: dict[str, Any] | None) -> RegressionReport:
        if baseline is None:
            return RegressionReport(True, None, {}, metrics, tuple(), tuple())
        old = baseline.get("metrics", {})
        regressions: dict[str, float] = {}
        improvements: dict[str, float] = {}
        unchanged = []
        blocking = []
        for capability in Capability.ALL:
            if capability in old and capability not in metrics:
                regressions[capability] = -1.0
                blocking.append(capability)
                continue
            if capability not in metrics or capability not in old:
                continue
            delta = metrics[capability] - old[capability]
            if delta < -self.max_regression:
                regressions[capability] = round(delta, 6)
                blocking.append(capability)
            elif delta > 0:
                improvements[capability] = round(delta, 6)
            else:
                unchanged.append(capability)
            if capability in {Capability.SECURITY, Capability.TOOLS} and delta < 0:
                blocking.append(capability)
        blocking = list(dict.fromkeys(blocking))
        return RegressionReport(not blocking, baseline.get("run_id"), regressions, improvements, tuple(unchanged), tuple(blocking))
