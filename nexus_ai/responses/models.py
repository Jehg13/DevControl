"""Contracts for rendering verified Nexus responses."""

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True)
class ResponseInput:
    message: str
    interpretation: dict[str, Any] = field(default_factory=dict)
    context: dict[str, Any] = field(default_factory=dict)
    memory: list[dict[str, Any]] = field(default_factory=list)
    knowledge: list[dict[str, Any]] = field(default_factory=list)
    reasoning: dict[str, Any] = field(default_factory=dict)
    plan: list[dict[str, Any]] = field(default_factory=list)
    tool_results: list[dict[str, Any]] = field(default_factory=list)
    grounding: dict[str, Any] = field(default_factory=dict)
    execution_status: str | None = None
    conversation: list[dict[str, str]] = field(default_factory=list)

    def __post_init__(self) -> None:
        if not self.message.strip():
            raise ValueError("message must be non-empty")


@dataclass(frozen=True)
class ResponseResult:
    text: str
    status: str
    sections: dict[str, list[str]]
    conversation: list[dict[str, str]]
    verified: bool = False
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        return {
            "text": self.text,
            "status": self.status,
            "sections": self.sections,
            "conversation": self.conversation,
            "verified": self.verified,
            "executable": self.executable,
        }
