from __future__ import annotations

import hashlib
import json
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Callable, Iterable


CHECKS = (
    "project",
    "dependencies",
    "tests",
    "errors",
    "infrastructure",
    "github",
)
SEVERITIES = ("INFO", "LOW", "MEDIUM", "HIGH", "CRITICAL")
DANGEROUS_ACTIONS = {"delete", "deploy", "dependency_upgrade", "modify_production"}


@dataclass(frozen=True)
class MaintenanceFinding:
    finding_id: str
    check: str
    title: str
    evidence: tuple[str, ...]
    severity: str
    confidence: float
    project: str
    location: str = ""
    recommendation: str = ""

    def to_devcontrol_task(self) -> dict:
        return {
            "title": self.title,
            "description": self.recommendation,
            "project": self.project,
            "severity": self.severity,
            "location": self.location,
            "evidence": list(self.evidence),
            "source": "nexus.maintenance",
            "finding_id": self.finding_id,
            "status": "proposed",
        }


@dataclass(frozen=True)
class MaintenanceTask:
    task_id: str
    finding_id: str
    title: str
    status: str
    project: str
    approval_required: bool


@dataclass(frozen=True)
class MaintenanceAction:
    action_id: str
    finding_id: str
    action: str
    status: str
    approval_required: bool
    result: str = ""


@dataclass(frozen=True)
class MaintenanceRun:
    run_id: str
    project: str
    started_at: str
    completed_at: str
    checks: tuple[str, ...]
    findings: tuple[MaintenanceFinding, ...]
    tasks: tuple[MaintenanceTask, ...]
    actions: tuple[MaintenanceAction, ...]
    audit_file: str


class MaintenanceService:
    """Runs periodic reviews without silently changing the project."""

    def __init__(
        self,
        audit_directory: str | Path,
        *,
        task_sink: Callable[[dict], None] | None = None,
        safe_action_runner: Callable[[str, MaintenanceFinding], str] | None = None,
    ) -> None:
        self.audit_directory = Path(audit_directory)
        self.task_sink = task_sink
        self.safe_action_runner = safe_action_runner

    def run(
        self,
        project: str,
        reviewers: dict[str, Callable[[], Iterable[dict]]],
        *,
        permissions: set[str] | None = None,
        approved_actions: set[str] | None = None,
        checks: Iterable[str] = CHECKS,
    ) -> MaintenanceRun:
        if not project.strip():
            raise ValueError("project is required")
        permissions = permissions or set()
        approved_actions = approved_actions or set()
        selected = tuple(checks)
        unknown = set(selected) - set(CHECKS)
        if unknown:
            raise ValueError(f"unknown maintenance checks: {sorted(unknown)}")
        started = datetime.now(timezone.utc).isoformat()
        findings: list[MaintenanceFinding] = []
        for check in selected:
            reviewer = reviewers.get(check)
            if reviewer is None:
                continue
            for raw in reviewer():
                finding = self._finding(project, check, raw)
                findings.append(finding)
        findings.sort(key=lambda item: (-SEVERITIES.index(item.severity), item.finding_id))
        tasks = tuple(self._task(finding) for finding in findings)
        for task in tasks:
            if self.task_sink is not None:
                self.task_sink(next(item.to_devcontrol_task() for item in findings if item.finding_id == task.finding_id))
        actions = tuple(
            self._action(finding, permissions, approved_actions)
            for finding in findings
            if finding.recommendation
        )
        completed = datetime.now(timezone.utc).isoformat()
        run_id = hashlib.sha256(
            json.dumps(
                {"project": project, "started": started, "findings": [asdict(item) for item in findings]},
                sort_keys=True,
                default=list,
            ).encode()
        ).hexdigest()[:16]
        self.audit_directory.mkdir(parents=True, exist_ok=True)
        audit_file = self.audit_directory / f"maintenance-{run_id}.json"
        run = MaintenanceRun(
            run_id, project, started, completed, selected, tuple(findings),
            tasks, actions, str(audit_file),
        )
        audit_file.write_text(json.dumps(asdict(run), ensure_ascii=True, indent=2) + "\n", encoding="utf-8")
        return run

    @staticmethod
    def _finding(project: str, check: str, raw: dict) -> MaintenanceFinding:
        title = str(raw.get("title", f"{check} review finding"))
        evidence = tuple(str(value) for value in raw.get("evidence", ()))
        severity = str(raw.get("severity", "INFO")).upper()
        if severity not in SEVERITIES:
            raise ValueError(f"invalid severity: {severity}")
        confidence = float(raw.get("confidence", 0))
        if not 0 <= confidence <= 1:
            raise ValueError("finding confidence must be between 0 and 1")
        digest = hashlib.sha256(
            json.dumps({"project": project, "check": check, "title": title, "evidence": evidence}, sort_keys=True).encode()
        ).hexdigest()[:16]
        return MaintenanceFinding(
            digest, check, title, evidence, severity, confidence, project,
            str(raw.get("location", "")), str(raw.get("recommendation", "")),
        )

    @staticmethod
    def _task(finding: MaintenanceFinding) -> MaintenanceTask:
        return MaintenanceTask(
            f"task-{finding.finding_id}", finding.finding_id, finding.title,
            "proposed", finding.project, finding.severity in {"HIGH", "CRITICAL"},
        )

    def _action(
        self,
        finding: MaintenanceFinding,
        permissions: set[str],
        approved_actions: set[str],
    ) -> MaintenanceAction:
        action = self._action_name(finding)
        action_id = f"action-{finding.finding_id}"
        dangerous = action in DANGEROUS_ACTIONS or finding.severity in {"HIGH", "CRITICAL"}
        permission = f"nexus.maintenance.{action}"
        if dangerous and action not in approved_actions:
            return MaintenanceAction(action_id, finding.finding_id, action, "approval_required", True)
        if permission not in permissions:
            return MaintenanceAction(action_id, finding.finding_id, action, "permission_required", dangerous)
        if self.safe_action_runner is None:
            return MaintenanceAction(action_id, finding.finding_id, action, "proposed", dangerous)
        result = self.safe_action_runner(action, finding)
        return MaintenanceAction(action_id, finding.finding_id, action, "completed", dangerous, result)

    @staticmethod
    def _action_name(finding: MaintenanceFinding) -> str:
        if finding.check == "dependencies":
            return "dependency_upgrade"
        if finding.check == "infrastructure":
            return "modify_production"
        if finding.check == "github":
            return "deploy"
        return "investigate"
