import tempfile
import unittest
from pathlib import Path

from nexus_maintenance import MaintenanceService


class NexusMaintenanceTest(unittest.TestCase):
    def test_runs_checks_prioritizes_tasks_and_writes_devcontrol_audit(self):
        tasks = []
        with tempfile.TemporaryDirectory() as directory:
            run = MaintenanceService(directory, task_sink=tasks.append).run(
                "devcontrol",
                {
                    "dependencies": lambda: [{
                        "title": "Outdated dependency",
                        "evidence": ["lockfile is outdated"],
                        "severity": "MEDIUM",
                        "confidence": 0.8,
                        "recommendation": "Review compatibility.",
                    }],
                    "tests": lambda: [{
                        "title": "Tests failing",
                        "evidence": ["test suite failed"],
                        "severity": "HIGH",
                        "confidence": 0.9,
                        "recommendation": "Investigate failures.",
                    }],
                },
                permissions={"nexus.maintenance.investigate"},
                checks=("dependencies", "tests"),
            )
            self.assertEqual(run.findings[0].severity, "HIGH")
            self.assertEqual(run.actions[0].status, "approval_required")
            self.assertEqual(len(tasks), 2)
            self.assertTrue(Path(run.audit_file).exists())

    def test_dangerous_action_requires_approval_even_with_permission(self):
        with tempfile.TemporaryDirectory() as directory:
            runner_calls = []
            run = MaintenanceService(
                directory,
                safe_action_runner=lambda action, finding: runner_calls.append(action) or "ok",
            ).run(
                "project",
                {"github": lambda: [{
                    "title": "Deployment concern",
                    "evidence": ["release pending"],
                    "severity": "HIGH",
                    "confidence": 0.9,
                    "recommendation": "Review release.",
                }]},
                permissions={"nexus.maintenance.deploy"},
                checks=("github",),
            )
            self.assertEqual(run.actions[0].status, "approval_required")
            self.assertEqual(runner_calls, [])

    def test_approved_safe_action_can_run(self):
        with tempfile.TemporaryDirectory() as directory:
            run = MaintenanceService(
                directory,
                safe_action_runner=lambda action, finding: "reviewed",
            ).run(
                "project",
                {"project": lambda: [{
                    "title": "Review project",
                    "evidence": ["new files"],
                    "severity": "LOW",
                    "confidence": 0.7,
                    "recommendation": "Investigate.",
                }]},
                permissions={"nexus.maintenance.investigate"},
                approved_actions={"investigate"},
                checks=("project",),
            )
            self.assertEqual(run.actions[0].status, "completed")


if __name__ == "__main__":
    unittest.main()
