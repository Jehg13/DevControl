"""Contracts for plans that Laravel may review before execution."""

from dataclasses import asdict, dataclass, field
from typing import Any


@dataclass(frozen=True)
class PlanRequest:
    objective: str
    available_data: dict[str, Any] = field(default_factory=dict)
    permissions: list[str] = field(default_factory=list)
    tools: list[dict[str, Any]] = field(default_factory=list)

    def __post_init__(self) -> None:
        if not self.objective.strip():
            raise ValueError("objective must be non-empty")


@dataclass(frozen=True)
class PlanStep:
    number: int
    objective: str
    required_data: list[str]
    depends_on: list[int]
    potential_tool: str | None
    expected_result: str
    risk: str
    requires_confirmation: bool = False
    required_permission: str | None = None
    executable: bool = False
    feasible: bool = True
    blocker: str | None = None

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class PlanResult:
    objective: str
    steps: list[PlanStep]
    missing_information: list[str]
    status: str
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["steps"] = [step.to_dict() for step in self.steps]
        return result
