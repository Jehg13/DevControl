"""A non-executing bridge for proposals exchanged with Laravel Nexus Core."""

from dataclasses import asdict, dataclass, field
from typing import Any


class ToolBridgeError(ValueError):
    """Raised when a Laravel tool contract cannot be safely consumed."""


@dataclass(frozen=True)
class ToolProposal:
    tool_name: str
    parameters: dict[str, Any]
    operation: str
    permissions: list[str]
    requires_confirmation: bool
    status: str = "proposed"
    executable: bool = False
    source: str = "nexus_ai_python"

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class ToolExecutionResult:
    successful: bool
    data: Any = None
    error: dict[str, Any] | None = None
    meta: dict[str, Any] = field(default_factory=dict)

    @classmethod
    def from_laravel(cls, payload: dict[str, Any]) -> "ToolExecutionResult":
        if not isinstance(payload, dict) or not isinstance(payload.get("ok"), bool):
            raise ToolBridgeError("Laravel tool results require a boolean ok field")
        return cls(payload["ok"], payload.get("data"), payload.get("error"), payload.get("meta", {}))


class NexusLaravelToolBridge:
    """Validates definitions and creates proposals; Laravel executes them."""

    _PROTECTED_ARGUMENTS = {"permission", "policy", "restriction", "weights", "training"}
    _WRITE_MARKERS = {"write", "create", "update", "delete", "destroy", "publish", "execute"}

    def __init__(self, definitions: list[dict[str, Any]]):
        self._definitions: dict[str, dict[str, Any]] = {}
        for definition in definitions:
            self._register(definition)

    def _register(self, definition: dict[str, Any]) -> None:
        name = definition.get("name")
        if not isinstance(name, str) or not name.strip():
            raise ToolBridgeError("tool definitions require a non-empty name")
        if name in self._definitions:
            raise ToolBridgeError(f"duplicate tool definition: {name}")
        permissions = definition.get("permissions", [])
        if not isinstance(permissions, list) or not all(isinstance(item, str) for item in permissions):
            raise ToolBridgeError(f"invalid permissions for tool: {name}")
        self._definitions[name] = {
            "name": name,
            "permissions": permissions,
            "requires_confirmation": bool(definition.get("requires_confirmation", False)),
            "parameters": definition.get("parameters", {}),
        }

    def definitions(self) -> list[dict[str, Any]]:
        return [dict(definition) for definition in self._definitions.values()]

    def propose(
        self,
        tool_name: str,
        parameters: dict[str, Any] | None = None,
        *,
        requested_permissions: list[str] | None = None,
    ) -> ToolProposal:
        definition = self._definitions.get(tool_name)
        if definition is None:
            raise ToolBridgeError("tool_not_found")
        values = parameters or {}
        if not isinstance(values, dict):
            raise ToolBridgeError("tool parameters must be an object")
        if self._PROTECTED_ARGUMENTS.intersection(values):
            raise ToolBridgeError("protected_argument")
        declared = definition["permissions"]
        requested = requested_permissions if requested_permissions is not None else declared
        if sorted(set(requested)) != sorted(set(declared)):
            raise ToolBridgeError("permission_mismatch")
        operation = "write" if any(
            marker in tool_name.casefold() or any(marker in permission.casefold() for permission in declared)
            for marker in self._WRITE_MARKERS
        ) else "read"
        return ToolProposal(
            tool_name,
            values,
            operation,
            list(declared),
            definition["requires_confirmation"] or operation == "write",
        )

    def accept_result(self, proposal: ToolProposal, payload: dict[str, Any]) -> ToolExecutionResult:
        if proposal.source != "nexus_ai_python" or proposal.executable:
            raise ToolBridgeError("invalid_proposal_origin")
        return ToolExecutionResult.from_laravel(payload)
