from __future__ import annotations

import subprocess
from dataclasses import dataclass
from enum import Enum
from pathlib import Path
from typing import Callable, Iterable


class WorkflowState(str, Enum):
    OBJECTIVE = "objective"
    ANALYSIS = "analysis"
    UNDERSTANDING = "understanding"
    PLAN = "plan"
    PROPOSAL = "proposal"
    PERMISSIONS = "permissions"
    MODIFICATION = "modification"
    TESTS = "tests"
    OBSERVATION = "observation"
    REFLECTION = "reflection"
    CORRECTION = "correction"
    VALIDATION = "validation"
    ROLLED_BACK = "rolled_back"


@dataclass(frozen=True)
class FileOperation:
    action: str
    path: str
    content: str | None = None


@dataclass(frozen=True)
class CodingPlan:
    objective: str
    analysis: str
    understanding: str
    steps: tuple[str, ...]
    operations: tuple[FileOperation, ...]
    tests: tuple[str, ...]
    expected_result: str


@dataclass(frozen=True)
class CodingResult:
    status: str
    state: str
    states: tuple[str, ...]
    plan: CodingPlan
    changed_files: tuple[str, ...]
    tests: tuple[dict, ...]
    reflection: str
    validation: str
    commit_proposal: dict | None = None
    pull_request_proposal: dict | None = None
    rollback_available: bool = False
    error: str | None = None


class AutonomousCoding:
    """Executes an explicit plan only after permissions and change checks."""

    def __init__(
        self,
        workspace: str | Path,
        *,
        test_runner: Callable[[str, Path], dict] | None = None,
        command_runner: Callable[[str, Path], dict] | None = None,
    ) -> None:
        self.workspace = Path(workspace).resolve()
        self.test_runner = test_runner or self._default_test_runner
        self.command_runner = command_runner or self._default_command_runner

    def execute(
        self,
        plan: CodingPlan,
        permissions: set[str],
        *,
        human_change_check: Callable[[], bool] | None = None,
        expected_snapshot: dict[str, bytes] | None = None,
        prepare_commit: bool = False,
        prepare_pull_request: bool = False,
    ) -> CodingResult:
        states = [WorkflowState.OBJECTIVE.value]
        if not plan.objective.strip():
            return self._failure(plan, states, "objective is required")
        states.extend([
            WorkflowState.ANALYSIS.value,
            WorkflowState.UNDERSTANDING.value,
            WorkflowState.PLAN.value,
            WorkflowState.PROPOSAL.value,
            WorkflowState.PERMISSIONS.value,
        ])
        required = {self._permission_for(operation) for operation in plan.operations}
        missing = required - permissions
        if missing:
            return self._failure(plan, states, f"missing permissions: {sorted(missing)}")
        baseline = self.snapshot()
        if expected_snapshot is not None and baseline != expected_snapshot:
            return self._failure(plan, states, "workspace changed since analysis; human changes detected")
        if human_change_check is not None and not human_change_check():
            return self._failure(plan, states, "human changes detected before modification")
        states.append(WorkflowState.MODIFICATION.value)
        rollback = dict(baseline)
        changed: list[str] = []
        try:
            for operation in plan.operations:
                path = self._safe_path(operation.path)
                self._apply(operation, path)
                changed.append(operation.path)
            states.append(WorkflowState.TESTS.value)
            test_results = tuple(self.test_runner(test, self.workspace) for test in plan.tests)
            if any(result.get("status") != "passed" for result in test_results):
                self._restore(rollback)
                states.append(WorkflowState.ROLLED_BACK.value)
                return self._failure(
                    plan, states, "tests failed; workspace restored", tuple(changed), test_results
                )
            states.extend([
                WorkflowState.OBSERVATION.value,
                WorkflowState.REFLECTION.value,
                WorkflowState.CORRECTION.value,
                WorkflowState.VALIDATION.value,
            ])
            current = self.snapshot()
            validation = "workspace matches the planned operations and tests passed"
            commit = self._commit_proposal(plan, changed) if prepare_commit else None
            pull_request = self._pull_request_proposal(plan, changed) if prepare_pull_request else None
            return CodingResult(
                "completed",
                states[-1],
                tuple(states),
                plan,
                tuple(changed),
                test_results,
                self._reflection(plan, current),
                validation,
                commit,
                pull_request,
                True,
            )
        except (OSError, ValueError, FileExistsError) as error:
            self._restore(rollback)
            states.append(WorkflowState.ROLLED_BACK.value)
            return self._failure(plan, states, str(error), tuple(changed))

    def snapshot(self) -> dict[str, bytes]:
        snapshot: dict[str, bytes] = {}
        for path in self.workspace.rglob("*"):
            if path.is_file() and ".git" not in path.parts:
                relative = path.relative_to(self.workspace).as_posix()
                snapshot[relative] = path.read_bytes()
        return snapshot

    def _safe_path(self, relative: str) -> Path:
        candidate = (self.workspace / relative).resolve()
        if candidate != self.workspace and self.workspace not in candidate.parents:
            raise ValueError(f"path escapes workspace: {relative}")
        return candidate

    @staticmethod
    def _permission_for(operation: FileOperation) -> str:
        return {
            "create": "nexus.code.create",
            "modify": "nexus.code.modify",
            "delete": "nexus.code.delete",
        }.get(operation.action, "nexus.code.modify")

    @staticmethod
    def _apply(operation: FileOperation, path: Path) -> None:
        if operation.action not in {"create", "modify", "delete"}:
            raise ValueError(f"unsupported file operation: {operation.action}")
        if operation.action == "delete":
            if path.exists():
                path.unlink()
            return
        if operation.content is None:
            raise ValueError(f"content is required for {operation.action}: {operation.path}")
        if operation.action == "create" and path.exists():
            raise FileExistsError(operation.path)
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(operation.content, encoding="utf-8", newline="\n")

    def _restore(self, baseline: dict[str, bytes]) -> None:
        current_files = {
            path.relative_to(self.workspace).as_posix(): path
            for path in self.workspace.rglob("*")
            if path.is_file() and ".git" not in path.parts
        }
        for relative in set(current_files) - set(baseline):
            current_files[relative].unlink()
        for relative, content in baseline.items():
            path = self.workspace / relative
            path.parent.mkdir(parents=True, exist_ok=True)
            path.write_bytes(content)

    def _reflection(self, plan: CodingPlan, current: dict[str, str]) -> str:
        return f"Applied {len(plan.operations)} planned operations; observed {len(current)} workspace files."

    @staticmethod
    def _default_test_runner(command: str, workspace: Path) -> dict:
        completed = subprocess.run(
            command,
            cwd=workspace,
            shell=True,
            capture_output=True,
            text=True,
            check=False,
        )
        return {
            "command": command,
            "status": "passed" if completed.returncode == 0 else "failed",
            "returncode": completed.returncode,
            "stdout": completed.stdout[-4000:],
            "stderr": completed.stderr[-4000:],
        }

    @staticmethod
    def _default_command_runner(command: str, workspace: Path) -> dict:
        return AutonomousCoding._default_test_runner(command, workspace)

    @staticmethod
    def _commit_proposal(plan: CodingPlan, changed: Iterable[str]) -> dict:
        return {"status": "proposed", "message": plan.objective, "files": list(changed)}

    @staticmethod
    def _pull_request_proposal(plan: CodingPlan, changed: Iterable[str]) -> dict:
        return {"status": "proposed", "title": plan.objective, "files": list(changed)}

    @staticmethod
    def _failure(plan, states, error, changed=(), tests=()):
        return CodingResult("failed", states[-1], tuple(states), plan, tuple(changed), tuple(tests), "", "", error=error)
