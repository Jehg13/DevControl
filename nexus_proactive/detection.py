from __future__ import annotations

import hashlib
import json
import re
from collections import Counter
from dataclasses import asdict, dataclass, field
from pathlib import Path
from typing import Iterable


LEVELS = ("INFO", "LOW", "MEDIUM", "HIGH", "CRITICAL")


@dataclass(frozen=True)
class DetectionInput:
    project: str
    commits: tuple[str, ...] = ()
    changes: tuple[str, ...] = ()
    errors: tuple[str, ...] = ()
    logs: tuple[str, ...] = ()
    metrics: tuple[str, ...] = ()
    incidents: tuple[str, ...] = ()
    bugs: tuple[str, ...] = ()
    infrastructure: tuple[str, ...] = ()
    dependencies: tuple[str, ...] = ()
    history: tuple[str, ...] = ()
    experiences: tuple[str, ...] = ()
    location: str = ""
    metadata: dict[str, str] = field(default_factory=dict)


@dataclass(frozen=True)
class ProactiveAlert:
    alert_id: str
    problem: str
    evidence: tuple[str, ...]
    severity: str
    confidence: float
    impact: str
    project: str
    location: str
    recommendation: str
    sources: tuple[str, ...]
    status: str = "proposed"

    def to_devcontrol(self) -> dict:
        """Return an incident-compatible payload without creating an incident."""
        return {
            "title": self.problem,
            "description": self.recommendation,
            "severity": self.severity,
            "confidence": self.confidence,
            "project": self.project,
            "location": self.location,
            "evidence": list(self.evidence),
            "sources": list(self.sources),
            "status": self.status,
            "proactive": True,
            "alert_id": self.alert_id,
        }


class ProactiveDetector:
    """Correlates independent signals and suppresses weak, duplicate alerts."""

    _patterns = (
        ("regression", r"\b(regression|reverted|previously fixed)\b", "HIGH"),
        ("recurring error", r"\b(repeated|recurring|again|N times|recurrent)\b", "MEDIUM"),
        ("dangerous change", r"\b(drop table|delete production|breaking change|force push)\b", "CRITICAL"),
        ("degradation", r"\b(?:high latency|latency (?:increased|spike|degraded)|slow|degrad(?:ed|ation)|timeout|error rate (?:increased|spike|high))\b", "HIGH"),
        ("anomaly", r"\b(anomal|spike|threshold|unusual|unexpected)\b", "MEDIUM"),
        ("suspicious configuration", r"\b(debug\s*=\s*true|missing env|default password|public access)\b", "HIGH"),
        ("problematic dependency", r"\b(vulnerable|deprecated|incompatible|outdated dependency)\b", "MEDIUM"),
        ("potential failure", r"\b(fail|failed|failure|crash|incident|bug)\b", "LOW"),
    )

    def detect(self, value: DetectionInput, min_confidence: float = 0.45) -> tuple[ProactiveAlert, ...]:
        if not value.project.strip():
            raise ValueError("project is required")
        if not 0 <= min_confidence <= 1:
            raise ValueError("min_confidence must be between 0 and 1")
        groups = {
            "commit": value.commits,
            "change": value.changes,
            "error": value.errors,
            "log": value.logs,
            "metric": value.metrics,
            "incident": value.incidents,
            "bug": value.bugs,
            "infrastructure": value.infrastructure,
            "dependency": value.dependencies,
            "history": value.history,
            "experience": value.experiences,
        }
        found: dict[str, dict] = {}
        for source, items in groups.items():
            for item in items:
                for name, pattern, level in self._patterns:
                    if re.search(pattern, item, re.IGNORECASE):
                        candidate = found.setdefault(name, {"level": level, "evidence": [], "sources": set()})
                        candidate["evidence"].append(f"{source}: {item}")
                        candidate["sources"].add(source)
        alerts = []
        for problem, candidate in found.items():
            independent_sources = len(candidate["sources"])
            evidence_count = len(candidate["evidence"])
            confidence = min(0.98, 0.25 + independent_sources * 0.16 + min(evidence_count, 4) * 0.07)
            if confidence < min_confidence:
                continue
            severity = self._escalate(candidate["level"], independent_sources, value)
            evidence = tuple(dict.fromkeys(candidate["evidence"]))[:8]
            payload = json.dumps(
                {"project": value.project, "problem": problem, "evidence": evidence},
                sort_keys=True,
            )
            alert_id = hashlib.sha256(payload.encode()).hexdigest()[:16]
            alerts.append(ProactiveAlert(
                alert_id=alert_id,
                problem=problem,
                evidence=evidence,
                severity=severity,
                confidence=round(confidence, 4),
                impact=self._impact(severity),
                project=value.project,
                location=value.location,
                recommendation=self._recommendation(problem),
                sources=tuple(sorted(candidate["sources"])),
            ))
        if "recurring error" in found:
            alerts = [alert for alert in alerts if alert.problem != "potential failure"]
        return tuple(sorted(alerts, key=lambda item: (-LEVELS.index(item.severity), item.alert_id)))

    @staticmethod
    def _escalate(level: str, source_count: int, value: DetectionInput) -> str:
        index = LEVELS.index(level)
        if source_count >= 3:
            index = min(index + 1, len(LEVELS) - 1)
        combined = " ".join(value.commits + value.changes + value.logs).casefold()
        if "production" in combined and level in {"HIGH", "CRITICAL"}:
            index = min(index + 1, len(LEVELS) - 1)
        return LEVELS[index]

    @staticmethod
    def _impact(severity: str) -> str:
        return {
            "INFO": "No immediate service impact indicated.",
            "LOW": "Potentially localized impact; monitor and verify.",
            "MEDIUM": "Possible degradation or maintenance impact.",
            "HIGH": "Likely availability, correctness or deployment impact.",
            "CRITICAL": "Potential security, data-integrity or production outage impact.",
        }[severity]

    @staticmethod
    def _recommendation(problem: str) -> str:
        return {
            "regression": "Compare the change with the last known-good revision and run focused regression tests.",
            "recurring error": "Correlate occurrences, identify the common component and create a tracked remediation.",
            "dangerous change": "Pause execution, require explicit approval and review the change in an isolated environment.",
            "degradation": "Compare metrics against the baseline and inspect the most recent deployment and dependencies.",
            "anomaly": "Collect a wider time window and confirm the anomaly against an independent signal.",
            "suspicious configuration": "Review effective configuration without exposing secrets and apply a secure baseline.",
            "problematic dependency": "Check the lockfile, advisories and compatibility before upgrading or pinning.",
            "potential failure": "Collect reproduction steps, logs and recent changes before escalating the alert.",
        }[problem]

    @staticmethod
    def export_devcontrol(alerts: Iterable[ProactiveAlert], path: str | Path) -> None:
        payload = [alert.to_devcontrol() for alert in alerts]
        Path(path).write_text(json.dumps(payload, ensure_ascii=True, indent=2) + "\n", encoding="utf-8")
