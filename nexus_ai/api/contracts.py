"""Serializable contracts exchanged at the Laravel/Python boundary."""

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass(frozen=True)
class NexusIdentity:
    name: str = "Nexus"
    application: str = "DevControl"
    role: str = "assistant"

    @classmethod
    def from_dict(cls, value: Any) -> "NexusIdentity":
        if not isinstance(value, dict):
            return cls()
        return cls(
            name=str(value.get("name", cls.name)),
            application=str(value.get("application", cls.application)),
            role=str(value.get("role", cls.role)),
        )


@dataclass(frozen=True)
class AuthenticatedUser:
    id: int | None = None
    name: str | None = None
    role: str | None = None

    @classmethod
    def from_dict(cls, value: Any) -> "AuthenticatedUser":
        if not isinstance(value, dict):
            return cls()
        user_id = value.get("id")
        return cls(
            id=int(user_id) if isinstance(user_id, int) or (isinstance(user_id, str) and user_id.isdigit()) else None,
            name=value.get("name"),
            role=value.get("role"),
        )


@dataclass(frozen=True)
class ConversationTurn:
    role: str
    content: str

    @classmethod
    def from_dict(cls, value: Any) -> "ConversationTurn":
        if not isinstance(value, dict) or not isinstance(value.get("role"), str) or not isinstance(value.get("content"), str):
            raise ValueError("conversation turns require string role and content")
        return cls(value["role"], value["content"])


@dataclass(frozen=True)
class NexusContext:
    values: dict[str, Any] = field(default_factory=dict)
    selected_project: dict[str, Any] | None = None
    relevant_memory: list[dict[str, Any]] = field(default_factory=list)

    @classmethod
    def from_dict(cls, value: Any) -> "NexusContext":
        if not isinstance(value, dict):
            return cls()
        values = value.get("values", {})
        return cls(
            values=values if isinstance(values, dict) else {},
            selected_project=value.get("selected_project") if isinstance(value.get("selected_project"), dict) else None,
            relevant_memory=value.get("relevant_memory", []) if isinstance(value.get("relevant_memory", []), list) else [],
        )


@dataclass(frozen=True)
class ToolDescriptor:
    name: str
    description: str
    parameters: dict[str, Any] = field(default_factory=dict)
    permissions: list[str] = field(default_factory=list)
    requires_confirmation: bool = False

    @classmethod
    def from_dict(cls, value: Any) -> "ToolDescriptor":
        if not isinstance(value, dict) or not isinstance(value.get("name"), str):
            raise ValueError("tool descriptors require a name")
        return cls(
            name=value["name"],
            description=str(value.get("description", "")),
            parameters=value.get("parameters", {}) if isinstance(value.get("parameters", {}), dict) else {},
            permissions=value.get("permissions", []) if isinstance(value.get("permissions", []), list) else [],
            requires_confirmation=bool(value.get("requires_confirmation", False)),
        )


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

    @classmethod
    def from_dict(cls, value: Any) -> "NexusAiRequest":
        if not isinstance(value, dict):
            raise ValueError("request must be an object")
        conversation = value.get("conversation", [])
        tools = value.get("tools", [])
        if not isinstance(conversation, list) or not isinstance(tools, list):
            raise ValueError("conversation and tools must be arrays")
        return cls(
            message=value.get("message", ""),
            user=AuthenticatedUser.from_dict(value.get("user")),
            context=NexusContext.from_dict(value.get("context")),
            conversation=[ConversationTurn.from_dict(turn) for turn in conversation],
            permissions=value.get("permissions", []) if isinstance(value.get("permissions", []), list) else [],
            tools=[ToolDescriptor.from_dict(tool) for tool in tools],
            identity=NexusIdentity.from_dict(value.get("identity")),
            metadata=value.get("metadata", {}) if isinstance(value.get("metadata", {}), dict) else {},
        )


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
    status: str = "unresolved"
    tool_information: list[dict[str, Any]] = field(default_factory=list)
    errors: list[str] = field(default_factory=list)
    data_requests: list[dict[str, Any]] = field(default_factory=list)
    evidence: list[dict[str, Any]] = field(default_factory=list)
    grounding: dict[str, Any] = field(default_factory=dict)

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)
