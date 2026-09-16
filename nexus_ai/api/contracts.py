"""Serializable contracts exchanged at the Laravel/Python boundary."""

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass(frozen=True)
class NexusIdentity:
    name: str = "Nexus"
    application: str = "DevControl"
    role: str = "assistant"


@dataclass(frozen=True)
class AuthenticatedUser:
    id: int | None = None
    name: str | None = None
    role: str | None = None


@dataclass(frozen=True)
class ConversationTurn:
    role: str
    content: str


@dataclass(frozen=True)
class NexusContext:
    values: dict[str, Any] = field(default_factory=dict)
    selected_project: dict[str, Any] | None = None
    relevant_memory: list[dict[str, Any]] = field(default_factory=list)


@dataclass(frozen=True)
class ToolDescriptor:
    name: str
    description: str
    parameters: dict[str, Any] = field(default_factory=dict)
    permissions: list[str] = field(default_factory=list)
    requires_confirmation: bool = False


@dataclass(frozen=True)
class NexusAiRequest:
    message: str
    user: AuthenticatedUser = field(default_factory=AuthenticatedUser)
    context: NexusContext = field(default_factory=NexusContext)
    conversation: list[ConversationTurn] = field(default_factory=list)
    permissions: list[str] = field(default_factory=list)
    tools: list[ToolDescriptor] = field(default_factory=list)
    identity: NexusIdentity = field(default_factory=NexusIdentity)
    metadata: dict[str, Any] = field(default_factory=dict)

    def __post_init__(self) -> None:
        if not isinstance(self.message, str) or not self.message.strip():
            raise ValueError("message must be a non-empty string")

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class Interpretation:
    intent: str = "unclassified"
    entities: dict[str, Any] = field(default_factory=dict)
    confidence: str = "unavailable"


@dataclass(frozen=True)
class NexusAiResponse:
    interpretation: Interpretation
    context_used: dict[str, Any] = field(default_factory=dict)
    plan: list[dict[str, Any]] = field(default_factory=list)
    proposed_actions: list[dict[str, Any]] = field(default_factory=list)
    response: str = ""
    status: str = "not_implemented"
    tool_information: list[dict[str, Any]] = field(default_factory=list)
    errors: list[str] = field(default_factory=list)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
