from __future__ import annotations

import hashlib
import json
import re
from dataclasses import asdict, dataclass, field
from typing import Iterable


ERROR_PATTERNS = {
    "database": (
        r"sqlstate|queryexception|migration|relation .* does not exist|deadlock",
        "database",
    ),
    "authentication": (
        r"unauthorized|forbidden|invalid token|authentication|permission denied",
        "authentication",
    ),
    "null_reference": (
        r"undefined|null reference|nullpointer|cannot read propert",
        "runtime",
    ),
    "dependency": (
        r"class .* not found|module not found|cannot find module|dependency",
        "dependency",
    ),
    "configuration": (
        r"configuration|environment variable|missing .* env|connection refused",
        "configuration",
    ),
}


@dataclass(frozen=True)
class DiagnosticInput:
    diagnostic_id: str
    error: str = ""
    stack_trace: str = ""
    logs: tuple[str, ...] = ()
    code: tuple[str, ...] = ()
    recent_changes: tuple[str, ...] = ()
    dependencies: tuple[str, ...] = ()
    configuration: tuple[str, ...] = ()
    history: tuple[str, ...] = ()
    knowledge: tuple[str, ...] = ()
    experiences: tuple[str, ...] = ()
    reflections: tuple[str, ...] = ()
    metadata: dict[str, str] = field(default_factory=dict)


@dataclass(frozen=True)
class DiagnosticReport:
    diagnostic_id: str
    status: str
    probable_causes: tuple[str, ...]
    evidence: tuple[str, ...]
    impact: str
    severity: str
    alternatives: tuple[str, ...]
    proposed_solution: str
    confidence: float
    missing_information: tuple[str, ...]
    sources: tuple[str, ...]
    trace_id: str


class NexusDiagnosticAI:
    """Combines technical signals without claiming unsupported certainty."""

    def diagnose(self, value: DiagnosticInput) -> DiagnosticReport:
        corpus = "\n".join([
            value.error,
            value.stack_trace,
            *value.logs,
            *value.code,
            *value.recent_changes,
            *value.dependencies,
            *value.configuration,
            *value.history,
            *value.knowledge,
            *value.experiences,
            *value.reflections,
        ]).casefold()
        evidence: list[str] = []
        causes: list[str] = []
        alternatives: list[str] = []
        sources: list[str] = []
        for name, (pattern, source) in ERROR_PATTERNS.items():
            if re.search(pattern, corpus):
                causes.append(name)
                evidence.append(f"pattern:{name}")
                sources.append(source)
        for label, values in (
            ("log", value.logs),
            ("stack_trace", (value.stack_trace,) if value.stack_trace else ()),
            ("change", value.recent_changes),
            ("knowledge", value.knowledge),
            ("experience", value.experiences),
            ("reflection", value.reflections),
        ):
            for item in values:
                if item.strip():
                    evidence.append(f"{label}:{item.strip()}")
        missing: list[str] = []
        if not value.error and not value.stack_trace and not value.logs:
            missing.append("error, stack trace or logs")
        if not value.recent_changes:
            missing.append("recent changes")
        if not value.code:
            missing.append("relevant code")
        if not value.configuration:
            missing.append("configuration and environment")
        if not value.history and not value.experiences:
            missing.append("historical or experiential evidence")
        unique_causes = tuple(dict.fromkeys(causes))
        confidence = min(0.95, 0.2 + len(evidence) * 0.08 + len(unique_causes) * 0.1)
        sufficient = bool(evidence) and confidence >= 0.45
        if not sufficient:
            return self._report(
                value,
                "insufficient_evidence",
                (),
                tuple(evidence),
                "unknown",
                "unknown",
                (),
                "No tengo evidencia suficiente para proponer una solución segura.",
                min(confidence, 0.4),
                tuple(missing or ["reproduction steps and corroborating evidence"]),
                tuple(dict.fromkeys(sources)),
            )
        if len(unique_causes) > 1:
            alternatives = tuple(f"also investigate {cause}" for cause in unique_causes[1:])
        severity = self._severity(value, unique_causes)
        primary = unique_causes[0] if unique_causes else "unclassified_failure"
        return self._report(
            value,
            "supported",
            (f"Probable cause: {primary}.",),
            tuple(evidence),
            self._impact(value, severity),
            severity,
            alternatives,
            self._solution(primary),
            round(confidence, 4),
            tuple(missing),
            tuple(dict.fromkeys(sources)),
        )

    @staticmethod
    def _severity(value: DiagnosticInput, causes: tuple[str, ...]) -> str:
        text = " ".join((value.error, *value.logs)).casefold()
        if "production" in text or "data loss" in text or "security" in text:
            return "critical"
        if "500" in text or "outage" in text or len(causes) > 1:
            return "high"
        return "medium"

    @staticmethod
    def _impact(value: DiagnosticInput, severity: str) -> str:
        if severity == "critical":
            return "Potential security, availability or data-integrity impact; contain before remediation."
        if severity == "high":
            return "Likely service degradation or failed deployment; validate affected flows."
        return "Localized technical impact is likely; confirm affected component and tests."

    @staticmethod
    def _solution(cause: str) -> str:
        return {
            "database": "Inspect the failing query or migration, reproduce against a safe database, then validate rollback and tests.",
            "authentication": "Verify token, permission and identity-provider configuration without exposing credentials.",
            "null_reference": "Trace the value initialization, add a guarded path, and reproduce with a focused test.",
            "dependency": "Compare dependency lockfiles and environment versions, then reinstall in an isolated environment.",
            "configuration": "Compare effective configuration with the known-good environment and validate non-secret settings.",
        }.get(cause, "Collect reproduction evidence, isolate the affected component, and validate a minimal fix.")

    @staticmethod
    def _report(value, status, causes, evidence, impact, severity, alternatives, solution, confidence, missing, sources):
        payload = json.dumps({
            "diagnostic_id": value.diagnostic_id,
            "status": status,
            "evidence": evidence,
            "sources": sources,
        }, sort_keys=True)
        trace_id = hashlib.sha256(payload.encode()).hexdigest()
        return DiagnosticReport(
            value.diagnostic_id, status, tuple(causes), tuple(evidence), impact,
            severity, tuple(alternatives), solution, confidence, tuple(missing),
            tuple(sources), trace_id,
        )
