import tempfile
import unittest
from pathlib import Path

from nexus_autonomous import AutonomousCoding, CodingPlan, FileOperation


class NexusAutonomousCodingTest(unittest.TestCase):
    def plan(self, operations, tests=("unit",)):
        return CodingPlan("Add safe feature", "Analyze", "Understand", ("implement",), tuple(operations), tests, "Tests pass")

    def test_requires_permissions_and_detects_human_changes(self):
        with tempfile.TemporaryDirectory() as directory:
            runner = AutonomousCoding(directory, test_runner=lambda command, workspace: {"status": "passed"})
            plan = self.plan((FileOperation("create", "app/new.py", "print('ok')"),))
            denied = runner.execute(plan, set())
            self.assertEqual(denied.status, "failed")
            self.assertIn("missing permissions", denied.error)
            changed = runner.execute(plan, {"nexus.code.create"}, human_change_check=lambda: False)
            self.assertIn("human changes", changed.error)

    def test_applies_runs_tests_and_prepares_proposals(self):
        with tempfile.TemporaryDirectory() as directory:
            runner = AutonomousCoding(directory, test_runner=lambda command, workspace: {"status": "passed"})
            result = runner.execute(
                self.plan((FileOperation("create", "tests/test_new.py", "assert True"),)),
                {"nexus.code.create"},
                prepare_commit=True,
                prepare_pull_request=True,
            )
            self.assertEqual(result.status, "completed")
            self.assertEqual(result.state, "validation")
            self.assertTrue(Path(directory, "tests/test_new.py").exists())
            self.assertEqual(result.commit_proposal["status"], "proposed")
            self.assertEqual(result.pull_request_proposal["status"], "proposed")

    def test_failed_tests_rollback_new_files(self):
        with tempfile.TemporaryDirectory() as directory:
            runner = AutonomousCoding(directory, test_runner=lambda command, workspace: {"status": "failed"})
            result = runner.execute(
                self.plan((FileOperation("create", "new.py", "broken"),)),
                {"nexus.code.create"},
            )
            self.assertEqual(result.state, "rolled_back")
            self.assertFalse(Path(directory, "new.py").exists())

    def test_failed_tests_restore_modified_files(self):
        with tempfile.TemporaryDirectory() as directory:
            target = Path(directory, "existing.py")
            target.write_text("original", encoding="utf-8")
            runner = AutonomousCoding(directory, test_runner=lambda command, workspace: {"status": "failed"})
            result = runner.execute(
                self.plan((FileOperation("modify", "existing.py", "broken"),)),
                {"nexus.code.modify"},
            )
            self.assertEqual(result.state, "rolled_back")
            self.assertEqual(target.read_text(encoding="utf-8"), "original")


if __name__ == "__main__":
    unittest.main()
