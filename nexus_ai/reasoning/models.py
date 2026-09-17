"""Contracts for inspectable reasoning results."""

from dataclasses import asdict, dataclass, field
from typing import Any, Literal

FindingType = Literal[
    "fact", "observation", "hypothesis", "inference", "missing",
    "contradiction", "conclusion",
]


@dataclass(frozen=True)
class ReasoningFinding:
    finding_type: FindingType
    statement: str
    evidence: list[dict[str, Any]] = field(default_factory=list)
    confidence: float | None = None
    status: str = "active"

    def __post_init__(self) -> None:
        if self.finding_type not in {
            "fact", "observation", "hypothesis", "inference",
            "missing", "contradiction", "conclusion",
        }:
            raise ValueError("unsupported finding type")
        if not self.statement.strip():
            raise ValueError("statement must be non-empty")
        if self.finding_type in {"fact", "observation", "conclusion"} and not self.evidence:
            raise ValueError("facts require evidence")

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class ReasoningRequest:
    message: str
    recovered_data: dict[str, Any] = field(default_factory=dict)
    context: dict[str, Any] = field(default_factory=dict)

    def __post_init__(self) -> None:
        if not self.message.strip():
            raise ValueError("message must be non-empty")


@dataclass(frozen=True)
class ReasoningResult:
    interpretation: dict[str, Any]
    information_needed: list[str]
    findings: list[ReasoningFinding]
    reasoning_steps: list[str]
    status: str
    executable: bool = False
    conclusion: ReasoningFinding | None = None
    evidence_fingerprint: str = ""

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["findings"] = [finding.to_dict() for finding in self.findings]
        result["conclusion"] = self.conclusion.to_dict() if self.conclusion else None
        return result
