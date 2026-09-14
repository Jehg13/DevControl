"""Controlled continuous learning primitives for Nexus.

The module deliberately separates collecting an experience from validating it,
publishing a dataset, and approving a model.  A caller can therefore use the
same store from the autonomous runtime and from an operator-facing CLI without
ever turning an unreviewed interaction into training data.
"""

from .system import (
    ContinuousLearningSystem,
    DatasetVersion,
    EvaluationSet,
    Experiment,
    Experience,
    LearningStore,
    ModelVersion,
    OutcomeEvaluation,
    QualityGates,
    TrainingSchedule,
    ValidationResult,
)

LearningSystem = ContinuousLearningSystem
NexusLearningSystem = ContinuousLearningSystem

__all__ = [
    "ContinuousLearningSystem",
    "DatasetVersion",
    "EvaluationSet",
    "Experiment",
    "Experience",
    "LearningStore",
    "LearningSystem",
    "ModelVersion",
    "NexusLearningSystem",
    "OutcomeEvaluation",
    "QualityGates",
    "TrainingSchedule",
    "ValidationResult",
]
