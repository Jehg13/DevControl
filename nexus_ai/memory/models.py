"""Memory records and context snapshots kept separate from DevControl data."""

from dataclasses import asdict, dataclass, field
from datetime import datetime
from typing import Any


@dataclass(frozen=True)
class MemoryRecord:
    record_id: str
    session_id: str
    kind: str
    content: dict[str, Any]
    created_at: datetime
    expires_at: datetime | None = None
    source: str = "conversation"

    def is_expired(self, now: datetime) -> bool:
        return self.expires_at is not None and self.expires_at <= now

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["created_at"] = self.created_at.isoformat()
        result["expires_at"] = self.expires_at.isoformat() if self.expires_at else None
        return result


@dataclass(frozen=True)
class ContextReference:
    text: str
    resolved: bool
    candidates: list[dict[str, Any]] = field(default_factory=list)
    selected: dict[str, Any] | None = None
    requires_clarification: bool = False
    reason: str | None = None


@dataclass(frozen=True)
class ContextSnapshot:
    session_id: str
    recent_turns: list[dict[str, Any]]
    relevant_records: list[dict[str, Any]]
    resolved_references: list[ContextReference]
    contradictions: list[dict[str, Any]]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
