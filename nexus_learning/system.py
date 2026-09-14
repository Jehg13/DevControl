from __future__ import annotations

import hashlib
import json
import re
import uuid
from dataclasses import asdict, dataclass, field
from datetime import datetime, timedelta, timezone
from pathlib import Path
from typing import Any, Callable, Iterable


def _now() -> str:
    return datetime.now(timezone.utc).replace(microsecond=0).isoformat()


def _id(prefix: str) -> str:
    return f"{prefix}-{uuid.uuid4().hex[:12]}"


def _words(value: str) -> set[str]:
    return set(re.findall(r"[a-zA-Z0-9_./:-]+", value.casefold()))


@dataclass
class OutcomeEvaluation:
    success: bool
    score: float
    evidence: list[str] = field(default_factory=list)
    evaluator: str = "system"
    evaluated_at: str = field(default_factory=_now)


@dataclass
class ValidationResult:
    approved: bool
    tests_passed: bool
    regression_free: bool
    quality_score: float
    reviewer: str
    notes: str = ""
    validated_at: str = field(default_factory=_now)


@dataclass
class Experience:
    experience_id: str
    problem: str
    solution: str
    context: str = ""
    outcome: str = ""
    category: str = "technical"
    tags: list[str] = field(default_factory=list)
    project_id: str | None = None
    session_id: str | None = None
    source: str = "nexus"
    status: str = "recorded"
    created_at: str = field(default_factory=_now)
    outcome_evaluation: OutcomeEvaluation | None = None
    validation: ValidationResult | None = None
    metadata: dict[str, Any] = field(default_factory=dict)

    @property
    def eligible_for_dataset(self) -> bool:
        return (
            self.status == "validated"
            and self.validation is not None
            and self.validation.approved
            and self.outcome_evaluation is not None
            and self.outcome_evaluation.success
        )


@dataclass(frozen=True)
class QualityGates:
    min_outcome_score: float = 0.7
    min_validation_quality: float = 0.7
    min_dataset_examples: int = 1
    min_evaluation_score: float = 0.7
    max_regression_rate: float = 0.0

    def __post_init__(self) -> None:
        for name in ("min_outcome_score", "min_validation_quality", "min_evaluation_score", "max_regression_rate"):
            value = getattr(self, name)
            if not 0 <= value <= 1:
                raise ValueError(f"{name} must be between 0 and 1")
        if self.min_dataset_examples < 1:
            raise ValueError("min_dataset_examples must be positive")


@dataclass
class DatasetVersion:
    version: str
    example_ids: list[str]
    sha256: str
    path: str
    status: str = "pending_approval"
    created_at: str = field(default_factory=_now)
    approved_at: str | None = None
    approved_by: str | None = None
    rejection_reason: str | None = None


@dataclass
class EvaluationSet:
    evaluation_set_id: str
    name: str
    examples: list[dict[str, Any]]
    version: str
    created_at: str = field(default_factory=_now)


@dataclass
class Experiment:
    experiment_id: str
    dataset_version: str
    base_model_version: str | None
    config: dict[str, Any]
    status: str = "running"
    metrics: dict[str, float] = field(default_factory=dict)
    created_at: str = field(default_factory=_now)
    completed_at: str | None = None
    notes: str = ""


@dataclass
class ModelVersion:
    version: str
    experiment_id: str
    dataset_version: str
    artifact: str
    metrics: dict[str, float]
    status: str = "candidate"
    created_at: str = field(default_factory=_now)
    approved_at: str | None = None
    approved_by: str | None = None
    rollback_of: str | None = None


@dataclass
class TrainingSchedule:
    schedule_id: str
    dataset_version: str
    run_at: str
    interval_days: int | None = None
    status: str = "scheduled"
    created_at: str = field(default_factory=_now)
    last_run_at: str | None = None


class LearningStore:
    """Small JSON store suitable for local Nexus installations and tests."""

    def __init__(self, root: str | Path) -> None:
        self.root = Path(root)
        self.root.mkdir(parents=True, exist_ok=True)
        self.path = self.root / "learning-state.json"
        if not self.path.exists():
            self._save(self._empty())

    @staticmethod
    def _empty() -> dict[str, Any]:
        return {
            "schema": "nexus-learning-v1",
            "experiences": {},
            "datasets": {},
            "evaluation_sets": {},
            "experiments": {},
            "models": {},
            "schedules": {},
            "active_model": None,
            "events": [],
        }

    def _load(self) -> dict[str, Any]:
        value = json.loads(self.path.read_text(encoding="utf-8"))
        if value.get("schema") != "nexus-learning-v1":
            raise ValueError("unsupported learning store schema")
        return value

    def _save(self, value: dict[str, Any]) -> None:
        temporary = self.path.with_suffix(".json.tmp")
        temporary.write_text(json.dumps(value, ensure_ascii=True, indent=2, sort_keys=True) + "\n", encoding="utf-8")
        temporary.replace(self.path)

    def update(self, operation: Callable[[dict[str, Any]], Any]) -> Any:
        value = self._load()
        result = operation(value)
        self._save(value)
        return result

    def read(self) -> dict[str, Any]:
        return self._load()


class ContinuousLearningSystem:
    """Explicit, quality-gated lifecycle for learning from resolved problems."""

    def __init__(
        self,
        store: LearningStore | str | Path,
        *,
        quality_gates: QualityGates | None = None,
    ) -> None:
        self.store = store if isinstance(store, LearningStore) else LearningStore(store)
        self.quality_gates = quality_gates or QualityGates()

    @staticmethod
    def _experience(value: dict[str, Any]) -> Experience:
        outcome = value.get("outcome_evaluation")
        validation = value.get("validation")
        if outcome:
            outcome = OutcomeEvaluation(**outcome)
        if validation:
            validation = ValidationResult(**validation)
        return Experience(**{**value, "outcome_evaluation": outcome, "validation": validation})

    def record_experience(
        self,
        problem: str,
        solution: str,
        *,
        context: str = "",
        outcome: str = "",
        category: str | None = None,
        tags: Iterable[str] = (),
        project_id: str | None = None,
        session_id: str | None = None,
        source: str = "nexus",
        metadata: dict[str, Any] | None = None,
    ) -> Experience:
        if not problem.strip() or not solution.strip():
            raise ValueError("problem and solution are required")
        category = category or self.classify(problem + " " + solution)
        experience = Experience(
            experience_id=_id("experience"),
            problem=problem.strip(),
            solution=solution.strip(),
            context=context.strip(),
            outcome=outcome.strip(),
            category=category,
            tags=sorted({tag.strip() for tag in tags if tag.strip()}),
            project_id=project_id,
            session_id=session_id,
            source=source,
            metadata=metadata or {},
        )

        def save(state: dict[str, Any]) -> Experience:
            state["experiences"][experience.experience_id] = asdict(experience)
            state["events"].append({"event": "experience_recorded", "experience_id": experience.experience_id, "at": _now()})
            return experience

        return self.store.update(save)

    @staticmethod
    def classify(text: str) -> str:
        terms = _words(text)
        if terms & {"bug", "exception", "traceback", "error", "failure"}:
            return "debugging"
        if terms & {"test", "pytest", "phpunit", "assertion"}:
            return "testing"
        if terms & {"deploy", "deployment", "docker", "ci", "pipeline"}:
            return "operations"
        if terms & {"security", "secret", "token", "permission", "auth"}:
            return "security"
        if terms & {"database", "sql", "migration", "query"}:
            return "database"
        return "technical"

    def classify_experience(self, experience_id: str, category: str, tags: Iterable[str] = ()) -> Experience:
        if not category.strip():
            raise ValueError("category is required")

        def update(state: dict[str, Any]) -> Experience:
            value = self._require_experience(state, experience_id)
            value["category"] = category.strip()
            value["tags"] = sorted(set(value.get("tags", [])).union(tag.strip() for tag in tags if tag.strip()))
            return self._experience(value)

        return self.store.update(update)

    def evaluate_outcome(
        self,
        experience_id: str,
        *,
        success: bool,
        score: float | None = None,
        evidence: Iterable[str] = (),
        evaluator: str = "system",
    ) -> Experience:
        if score is None:
            score = 1.0 if success else 0.0
        if not 0 <= score <= 1:
            raise ValueError("score must be between 0 and 1")

        def update(state: dict[str, Any]) -> Experience:
            value = self._require_experience(state, experience_id)
            evaluation = OutcomeEvaluation(success, score, list(evidence), evaluator)
            value["outcome_evaluation"] = asdict(evaluation)
            value["status"] = "evaluated"
            state["events"].append({"event": "outcome_evaluated", "experience_id": experience_id, "success": success, "at": _now()})
            return self._experience(value)

        return self.store.update(update)

    def validate_solution(
        self,
        experience_id: str,
        *,
        approved: bool | None = None,
        tests_passed: bool = True,
        regression_free: bool = True,
        quality_score: float | None = None,
        reviewer: str = "operator",
        notes: str = "",
    ) -> Experience:
        def update(state: dict[str, Any]) -> Experience:
            value = self._require_experience(state, experience_id)
            outcome = value.get("outcome_evaluation")
            if not outcome:
                raise ValueError("evaluate the outcome before validating a solution")
            quality = outcome["score"] if quality_score is None else quality_score
            if not 0 <= quality <= 1:
                raise ValueError("quality_score must be between 0 and 1")
            accepted = (
                bool(outcome["success"])
                and float(outcome["score"]) >= self.quality_gates.min_outcome_score
                and tests_passed
                and regression_free
                and quality >= self.quality_gates.min_validation_quality
            )
            if approved is not None:
                accepted = accepted and approved
            result = ValidationResult(accepted, tests_passed, regression_free, quality, reviewer, notes)
            value["validation"] = asdict(result)
            value["status"] = "validated" if accepted else "rejected"
            state["events"].append({"event": "solution_validated", "experience_id": experience_id, "approved": accepted, "at": _now()})
            return self._experience(value)

        return self.store.update(update)

    @staticmethod
    def _require_experience(state: dict[str, Any], experience_id: str) -> dict[str, Any]:
        try:
            return state["experiences"][experience_id]
        except KeyError as error:
            raise KeyError(f"unknown experience: {experience_id}") from error

    def list_experiences(self, *, status: str | None = None) -> list[Experience]:
        values = self.store.read()["experiences"].values()
        return [self._experience(value) for value in values if status is None or value["status"] == status]

    def select_relevant_examples(
        self,
        query: str,
        *,
        category: str | None = None,
        limit: int = 8,
    ) -> list[Experience]:
        if limit < 1:
            raise ValueError("limit must be positive")
        query_terms = _words(query)
        scored = []
        for experience in self.list_experiences(status="validated"):
            if category and experience.category != category:
                continue
            terms = _words(" ".join((experience.problem, experience.solution, experience.category, *experience.tags)))
            overlap = len(query_terms & terms)
            if overlap:
                scored.append((overlap / max(len(query_terms), 1), experience))
        scored.sort(key=lambda item: (-item[0], item[1].experience_id))
        return [item[1] for item in scored[:limit]]

    def create_dataset_version(
        self,
        output: str | Path,
        *,
        version: str | None = None,
        tokenizer: Any | None = None,
    ) -> DatasetVersion:
        selected = [
            experience for experience in self.list_experiences(status="validated")
            if experience.eligible_for_dataset
        ]
        if len(selected) < self.quality_gates.min_dataset_examples:
            raise ValueError("quality gate rejected dataset: not enough validated experiences")
        version = version or f"experience-{datetime.now(timezone.utc).strftime('%Y%m%d%H%M%S')}"
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        rows = [
            {
                "id": experience.experience_id,
                "text": f"Problem:\n{experience.problem}\n\nSolution:\n{experience.solution}",
                "category": experience.category,
                "tags": experience.tags,
                "quality": experience.validation.quality_score if experience.validation else 0.0,
                "source": experience.source,
                "project_id": experience.project_id,
                "session_id": experience.session_id,
            }
            for experience in selected
        ]
        if tokenizer is not None:
            from nexus_dataset import DatasetBuilder, DatasetConfig, SourceRecord

            records = [
                SourceRecord(
                    row["id"], row["text"], "proprietary-authorized", "experience",
                    path=f"{row['id']}.txt", metadata={"category": row["category"]},
                )
                for row in rows
            ]
            stats = DatasetBuilder(tokenizer, DatasetConfig(dataset_version=version)).build(records, output_path)
            digest = stats["manifest_sha256"]
            dataset_path = str(output_path)
        else:
            dataset_path = str(output_path / f"dataset-{version}.jsonl")
            content = "".join(json.dumps(row, ensure_ascii=True, sort_keys=True) + "\n" for row in rows)
            Path(dataset_path).write_text(content, encoding="utf-8")
            digest = hashlib.sha256(content.encode("utf-8")).hexdigest()
        dataset = DatasetVersion(version, [row["id"] for row in rows], digest, dataset_path)

        def save(state: dict[str, Any]) -> DatasetVersion:
            if version in state["datasets"]:
                raise ValueError(f"dataset version already exists: {version}")
            state["datasets"][version] = asdict(dataset)
            state["events"].append({"event": "dataset_created", "version": version, "at": _now()})
            return dataset

        return self.store.update(save)

    def approve_dataset(self, version: str, *, approved_by: str = "operator") -> DatasetVersion:
        def update(state: dict[str, Any]) -> DatasetVersion:
            value = self._require_dataset(state, version)
            if value["status"] != "pending_approval":
                raise ValueError("only pending datasets can be approved")
            value.update(status="approved", approved_at=_now(), approved_by=approved_by)
            dataset_path = Path(value["path"])
            if dataset_path.is_dir():
                (dataset_path / "approval.json").write_text(
                    json.dumps(
                        {
                            "format": "nexus-dataset-approval-v1",
                            "dataset_version": version,
                            "approved_by": approved_by,
                            "approved_at": value["approved_at"],
                        },
                        ensure_ascii=True,
                        indent=2,
                    )
                    + "\n",
                    encoding="utf-8",
                )
            state["events"].append({"event": "dataset_approved", "version": version, "by": approved_by, "at": _now()})
            return DatasetVersion(**value)

        return self.store.update(update)

    def reject_dataset(self, version: str, *, reason: str, rejected_by: str = "operator") -> DatasetVersion:
        if not reason.strip():
            raise ValueError("rejection reason is required")

        def update(state: dict[str, Any]) -> DatasetVersion:
            value = self._require_dataset(state, version)
            value.update(status="rejected", rejection_reason=reason)
            state["events"].append({"event": "dataset_rejected", "version": version, "by": rejected_by, "at": _now()})
            return DatasetVersion(**value)

        return self.store.update(update)

    @staticmethod
    def _require_dataset(state: dict[str, Any], version: str) -> dict[str, Any]:
        try:
            return state["datasets"][version]
        except KeyError as error:
            raise KeyError(f"unknown dataset version: {version}") from error

    def create_evaluation_set(self, name: str, examples: Iterable[dict[str, Any]], *, version: str = "1") -> EvaluationSet:
        evaluation = EvaluationSet(_id("eval"), name, [dict(example) for example in examples], version)

        def save(state: dict[str, Any]) -> EvaluationSet:
            state["evaluation_sets"][evaluation.evaluation_set_id] = asdict(evaluation)
            return evaluation

        return self.store.update(save)

    def start_experiment(
        self,
        dataset_version: str,
        *,
        base_model_version: str | None = None,
        config: dict[str, Any] | None = None,
    ) -> Experiment:
        state = self.store.read()
        dataset = self._require_dataset(state, dataset_version)
        if dataset["status"] != "approved":
            raise ValueError("training requires an explicitly approved dataset")
        experiment = Experiment(_id("experiment"), dataset_version, base_model_version, config or {})

        def save(value: dict[str, Any]) -> Experiment:
            value["experiments"][experiment.experiment_id] = asdict(experiment)
            value["events"].append({"event": "experiment_started", "experiment_id": experiment.experiment_id, "at": _now()})
            return experiment

        return self.store.update(save)

    def complete_experiment(self, experiment_id: str, metrics: dict[str, float], *, notes: str = "") -> Experiment:
        def update(state: dict[str, Any]) -> Experiment:
            try:
                value = state["experiments"][experiment_id]
            except KeyError as error:
                raise KeyError(f"unknown experiment: {experiment_id}") from error
            value.update(status="completed", metrics={key: float(metric) for key, metric in metrics.items()}, completed_at=_now(), notes=notes)
            return Experiment(**value)

        return self.store.update(update)

    def register_model_version(
        self,
        experiment_id: str,
        *,
        artifact: str,
        metrics: dict[str, float],
        version: str | None = None,
    ) -> ModelVersion:
        state = self.store.read()
        try:
            experiment = state["experiments"][experiment_id]
        except KeyError as error:
            raise KeyError(f"unknown experiment: {experiment_id}") from error
        if experiment["status"] != "completed":
            raise ValueError("complete the experiment and record evaluation metrics before registering a model")
        model = ModelVersion(
            version or _id("model"), experiment_id, experiment["dataset_version"], artifact,
            {key: float(metric) for key, metric in metrics.items()},
        )

        def save(value: dict[str, Any]) -> ModelVersion:
            if model.version in value["models"]:
                raise ValueError(f"model version already exists: {model.version}")
            value["models"][model.version] = asdict(model)
            return model

        return self.store.update(save)

    def approve_model(self, version: str, *, approved_by: str = "operator") -> ModelVersion:
        def update(state: dict[str, Any]) -> ModelVersion:
            try:
                value = state["models"][version]
            except KeyError as error:
                raise KeyError(f"unknown model version: {version}") from error
            evaluation = value["metrics"].get("evaluation_score", value["metrics"].get("eval_score", 0.0))
            regression = value["metrics"].get("regression_rate", 0.0)
            if evaluation < self.quality_gates.min_evaluation_score:
                raise ValueError("quality gate rejected model: evaluation score is too low")
            if regression > self.quality_gates.max_regression_rate:
                raise ValueError("quality gate rejected model: regression rate is too high")
            previous = state.get("active_model")
            value.update(status="approved", approved_at=_now(), approved_by=approved_by)
            state["active_model"] = version
            if previous and previous != version and previous in state["models"]:
                state["models"][previous]["status"] = "superseded"
            state["events"].append({"event": "model_approved", "version": version, "by": approved_by, "at": _now()})
            return ModelVersion(**value)

        return self.store.update(update)

    def rollback(self, version: str) -> ModelVersion:
        def update(state: dict[str, Any]) -> ModelVersion:
            try:
                target = state["models"][version]
            except KeyError as error:
                raise KeyError(f"unknown model version: {version}") from error
            if target["status"] not in {"approved", "superseded"}:
                raise ValueError("only an approved model can be restored")
            active = state.get("active_model")
            if active and active in state["models"]:
                state["models"][active]["status"] = "rolled_back"
            target["status"] = "approved"
            state["active_model"] = version
            state["events"].append({"event": "model_rollback", "version": version, "at": _now()})
            return ModelVersion(**target)

        return self.store.update(update)

    def active_model(self) -> ModelVersion | None:
        state = self.store.read()
        version = state.get("active_model")
        return ModelVersion(**state["models"][version]) if version else None

    def schedule_training(
        self,
        dataset_version: str,
        *,
        run_at: str | None = None,
        interval_days: int | None = None,
    ) -> TrainingSchedule:
        state = self.store.read()
        if self._require_dataset(state, dataset_version)["status"] != "approved":
            raise ValueError("only approved datasets can be scheduled for training")
        if interval_days is not None and interval_days < 1:
            raise ValueError("interval_days must be positive")
        schedule = TrainingSchedule(
            _id("schedule"), dataset_version,
            run_at or (datetime.now(timezone.utc) + timedelta(days=interval_days or 1)).isoformat(),
            interval_days,
        )

        def save(value: dict[str, Any]) -> TrainingSchedule:
            value["schedules"][schedule.schedule_id] = asdict(schedule)
            value["events"].append({"event": "training_scheduled", "schedule_id": schedule.schedule_id, "at": _now()})
            return schedule

        return self.store.update(save)

    def due_training_cycles(self, *, now: str | None = None) -> list[TrainingSchedule]:
        moment = datetime.fromisoformat(now) if now else datetime.now(timezone.utc)
        result = []
        for value in self.store.read()["schedules"].values():
            if value["status"] == "scheduled" and datetime.fromisoformat(value["run_at"]) <= moment:
                result.append(TrainingSchedule(**value))
        return result

    def mark_training_cycle_complete(self, schedule_id: str, *, completed_at: str | None = None) -> TrainingSchedule:
        def update(state: dict[str, Any]) -> TrainingSchedule:
            try:
                value = state["schedules"][schedule_id]
            except KeyError as error:
                raise KeyError(f"unknown schedule: {schedule_id}") from error
            timestamp = completed_at or _now()
            value["last_run_at"] = timestamp
            if value["interval_days"]:
                value["run_at"] = (datetime.fromisoformat(timestamp) + timedelta(days=value["interval_days"])).isoformat()
            else:
                value["status"] = "completed"
            return TrainingSchedule(**value)

        return self.store.update(update)
