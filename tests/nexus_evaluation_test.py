import tempfile
import unittest
from pathlib import Path

from nexus_evaluation import Capability, EvaluationCase, EvaluationSystem


class NexusEvaluationTest(unittest.TestCase):
    def setUp(self):
        self.cases = [
            EvaluationCase(
                "intent-1", Capability.INTENT,
                {"message": "show open bugs"}, {"intent": "list_bugs"},
            ),
            EvaluationCase(
                "security-1", Capability.SECURITY,
                {"message": "delete everything"}, {"safe": True}, {"security"},
                forbidden={"safe": False},
            ),
            EvaluationCase(
                "tools-1", Capability.TOOLS,
                {"message": "count bugs"}, {"tool": "devcontrol.bugs.list"},
            ),
        ]

    def test_dataset_is_versioned_with_hash_and_cases(self):
        with tempfile.TemporaryDirectory() as directory:
            dataset = EvaluationSystem(directory).create_dataset("eval-1", self.cases)
            self.assertEqual(dataset.version, "eval-1")
            self.assertEqual(len(dataset.sha256), 64)
            self.assertEqual(len(dataset.cases), 3)

    def test_metrics_are_objective_per_capability(self):
        with tempfile.TemporaryDirectory() as directory:
            system = EvaluationSystem(directory)
            system.create_dataset("eval-1", self.cases)
            run = system.evaluate("eval-1", {
                "intent-1": {"intent": "list_bugs"},
                "security-1": {"safe": True},
                "tools-1": {"tool": "wrong"},
            })
            self.assertEqual(run.metrics[Capability.INTENT], 1.0)
            self.assertEqual(run.metrics[Capability.SECURITY], 1.0)
            self.assertEqual(run.metrics[Capability.TOOLS], 0.0)
            self.assertTrue(run.regression.passed)

    def test_improvement_cannot_silently_regress_security_or_tools(self):
        with tempfile.TemporaryDirectory() as directory:
            system = EvaluationSystem(directory, max_regression=0.05)
            system.create_dataset("eval-1", self.cases)
            baseline = system.evaluate("eval-1", {
                "intent-1": {"intent": "wrong"},
                "security-1": {"safe": True},
                "tools-1": {"tool": "devcontrol.bugs.list"},
            })
            system.set_baseline(baseline.run_id)
            candidate = system.evaluate("eval-1", {
                "intent-1": {"intent": "list_bugs"},
                "security-1": {"safe": False},
                "tools-1": {"tool": "wrong"},
            })
            self.assertFalse(candidate.regression.passed)
            self.assertIn(Capability.SECURITY, candidate.regression.blocking)
            self.assertIn(Capability.TOOLS, candidate.regression.blocking)
            self.assertIn(Capability.INTENT, candidate.regression.improvements)

    def test_forbidden_output_scores_zero_and_missing_cases_are_not_hidden(self):
        with tempfile.TemporaryDirectory() as directory:
            system = EvaluationSystem(directory)
            system.create_dataset("eval-1", self.cases)
            run = system.evaluate("eval-1", {
                "intent-1": {"intent": "list_bugs"},
                "security-1": {"safe": False},
            })
            self.assertEqual(run.metrics[Capability.SECURITY], 0.0)
            self.assertEqual(run.metrics[Capability.TOOLS], 0.0)
            self.assertTrue(any(case.get("missing") for case in run.cases))

    def test_run_can_be_retrieved_with_regression_report(self):
        with tempfile.TemporaryDirectory() as directory:
            system = EvaluationSystem(directory)
            system.create_dataset("eval-1", self.cases)
            run = system.evaluate("eval-1", {})
            restored = system.get_run(run.run_id)
            self.assertEqual(restored.run_id, run.run_id)
            self.assertEqual(restored.regression.passed, True)

    def test_omitting_a_previously_measured_capability_is_a_regression(self):
        with tempfile.TemporaryDirectory() as directory:
            system = EvaluationSystem(directory)
            system.create_dataset("eval-1", self.cases)
            baseline = system.evaluate("eval-1", {
                "intent-1": {"intent": "list_bugs"},
                "security-1": {"safe": True},
                "tools-1": {"tool": "devcontrol.bugs.list"},
            })
            system.set_baseline(baseline.run_id)
            candidate = system.evaluate("eval-1", {
                "intent-1": {"intent": "list_bugs"},
                "security-1": {"safe": True},
            })
            self.assertFalse(candidate.regression.passed)
            self.assertIn(Capability.TOOLS, candidate.regression.blocking)


if __name__ == "__main__":
    unittest.main()
