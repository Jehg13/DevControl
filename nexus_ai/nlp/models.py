"""Structured, execution-free NLP representations."""

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass(frozen=True)
class NlpInterpretation:
    original_text: str
    normalized_text: str
    tokens: list[str]
    intent: str
    entity: str | None
    action: str
    entities: dict[str, Any] = field(default_factory=dict)
    filters: dict[str, Any] = field(default_factory=dict)
    temporal_references: list[dict[str, str]] = field(default_factory=list)
    contextual_references: list[dict[str, Any]] = field(default_factory=list)
    ambiguous: bool = False
    incomplete: bool = False
    requires_context: bool = False
    requires_clarification: bool = False
    requires_confirmation: bool = False
    executable: bool = False
    confidence: str = "low"
    clarification_question: str | None = None
    operation: str = "unknown"
    sort: dict[str, str] = field(default_factory=dict)
    limit: int | None = None
    context_requirements: list[str] = field(default_factory=list)
    related_entities: dict[str, list[str]] = field(default_factory=dict)
    negations: list[str] = field(default_factory=list)
    constraints: dict[str, Any] = field(default_factory=dict)
    inherited_context: dict[str, Any] = field(default_factory=dict)
    ambiguity_level: str = "none"
    group_by: list[str] = field(default_factory=list)
    relations: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
