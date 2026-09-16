"""Typed records used by the DevControl knowledge projection."""

from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from typing import Any, Literal

AssertionType = Literal["fact", "relation", "inference"]


@dataclass(frozen=True)
class KnowledgeNode:
    node_id: str
    node_type: str
    label: str | None = None
    attributes: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class KnowledgeAssertion:
    subject: str
    predicate: str
    object: Any
    assertion_type: AssertionType
    source: str
    confidence: float = 1.0
    observed_at: datetime = field(default_factory=lambda: datetime.now(timezone.utc))

    def __post_init__(self) -> None:
        if not self.subject.strip() or not self.predicate.strip() or not self.source.strip():
            raise ValueError("subject, predicate, and source must be non-empty")
        if self.assertion_type not in {"fact", "relation", "inference"}:
            raise ValueError("unsupported assertion type")
        if not 0.0 <= self.confidence <= 1.0:
            raise ValueError("confidence must be between 0 and 1")

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["observed_at"] = self.observed_at.isoformat()
        return result


@dataclass(frozen=True)
class KnowledgeSnapshot:
    source: str
    version: str
    nodes: tuple[KnowledgeNode, ...]
    assertions: tuple[KnowledgeAssertion, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "source": self.source,
            "version": self.version,
            "nodes": [node.to_dict() for node in self.nodes],
            "assertions": [assertion.to_dict() for assertion in self.assertions],
        }
