import json
import tempfile
import unittest
from pathlib import Path

from nexus_learning import ContinuousLearningSystem, QualityGates


class NexusLearningTest(unittest.TestCase):
    def _validated(self, system, *, success=True, score=1.0):
        experience = system.record_experience(
            "The query raises a database error",
            "Add a parameterized query and a regression test",
            tags=["sql"],
        )
        system.evaluate_outcome(experience.experience_id, success=success, score=score)
        return system.validate_solution(experience.experience_id)

    def test_unvalidated_and_bad_experiences_never_enter_dataset(self):
        with tempfile.TemporaryDirectory() as directory:
            system = ContinuousLearningSystem(directory)
            pending = system.record_experience("A problem", "An unverified fix")
            bad = self._validated(system, success=False, score=0.1)
            self.assertEqual(pending.status, "recorded")
            self.assertEqual(bad.status, "rejected")
            with self.assertRaises(ValueError):
                system.create_dataset_version(Path(directory) / "dataset")

    def test_dataset_approval_experiment_quality_gate_and_rollback(self):
        with tempfile.TemporaryDirectory() as directory:
            system = ContinuousLearningSystem(directory)
            experience = self._validated(system)
            dataset = system.create_dataset_version(Path(directory) / "dataset", version="d1")
            self.assertEqual(dataset.status, "pending_approval")
            with self.assertRaises(ValueError):
                system.start_experiment("d1")
            system.approve_dataset("d1", approved_by="reviewer")
            experiment = system.start_experiment("d1")
            system.complete_experiment(experiment.experiment_id, {"evaluation_score": 0.9})
            model = system.register_model_version(
                experiment.experiment_id,
                artifact="checkpoint.json",
                metrics={"evaluation_score": 0.9, "regression_rate": 0.0},
                version="m1",
            )
            with self.assertRaises(PermissionError):
                system.promote_model(model.version, promoted_by="reviewer")
            with self.assertRaises(PermissionError):
                system.approve_model(model.version, approved_by="core-reviewer")
            self.assertIsNone(system.active_model())
            self.assertEqual(system.select_relevant_examples("database query")[0].experience_id, experience.experience_id)
            self.assertEqual(json.loads(Path(dataset.path).read_text(encoding="utf-8"))["id"], experience.experience_id)

    def test_schedule_is_explicit_and_quality_gates_reject_regression(self):
        with tempfile.TemporaryDirectory() as directory:
            system = ContinuousLearningSystem(directory, quality_gates=QualityGates(max_regression_rate=0.0))
            self._validated(system)
            dataset = system.create_dataset_version(Path(directory) / "dataset", version="d1")
            system.approve_dataset(dataset.version)
            schedule = system.schedule_training(dataset.version, interval_days=7)
            self.assertEqual(system.due_training_cycles(now="2020-01-01T00:00:00+00:00"), [])
            experiment = system.start_experiment(dataset.version)
            system.complete_experiment(experiment.experiment_id, {"evaluation_score": 0.9})
            model = system.register_model_version(
                experiment.experiment_id,
                artifact="candidate",
                metrics={"evaluation_score": 0.9, "regression_rate": 0.1},
            )
            with self.assertRaises(PermissionError):
                system.approve_model(model.version)
            self.assertEqual(schedule.status, "scheduled")

    def test_integrity_rejects_duplicates_contradictions_and_contamination(self):
        with tempfile.TemporaryDirectory() as directory:
            system = ContinuousLearningSystem(directory)
            first = system.record_experience(
                "The cache fails after deployment",
                "Invalidate the cache and add a regression test",
                source="reviewed",
                tests=["regression test passed"],
            )
            second = system.record_experience(
                "The cache fails after deployment",
                "Restart the server instead",
                source="nexus",
                tests=["regression test passed"],
            )
            duplicate = system.record_experience(
                "The cache fails after deployment",
                "Restart the server instead",
                source="nexus",
                tests=["regression test passed"],
            )
            secret = system.record_experience(
                "The token is exposed",
                "Set password = 'supersecretvalue' in the deployment",
                source="nexus",
            )
            for experience in (first, second, duplicate, secret):
                system.evaluate_outcome(experience.experience_id, success=True, score=1.0)
                system.validate_solution(experience.experience_id, tests_passed=True, regression_free=True)

            integrity = system.detect_data_integrity()
            self.assertFalse(integrity["safe"])
            self.assertTrue(integrity["contaminated"])
            self.assertTrue(integrity["contradictions"])
            with self.assertRaises(ValueError):
                system.create_dataset_version(Path(directory) / "dataset")

    def test_dataset_version_keeps_metrics_and_provenance(self):
        with tempfile.TemporaryDirectory() as directory:
            system = ContinuousLearningSystem(directory)
            experience = self._validated(system)
            dataset = system.create_dataset_version(Path(directory) / "dataset", version="traceable")

            self.assertEqual(dataset.metrics["examples"], 1.0)
            self.assertEqual(dataset.provenance["experience_ids"], [experience.experience_id])
            self.assertEqual(dataset.provenance["integrity"]["safe"], True)


if __name__ == "__main__":
    unittest.main()
