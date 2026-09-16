import tempfile
import unittest
from pathlib import Path

from nexus_ai.ml.evolution import (
    EvolutionRegistry,
    LearningExample,
    append_validated_examples,
    validate_learning_example,
)
from nexus_ai.ml.pipeline import TrainingReport


class NexusEvolutionTests(unittest.TestCase):
    def _example(self, validated=True):
        return LearningExample(
            "candidate-1",
            "¿Qué bugs están abiertos?",
            {
                "intent": "consultar_bugs", "entity": "bug", "action": "query",
                "entities": {}, "filters": {"status": "Abierto"}, "content": "",
                "needs_clarification": False,
            },
            "correct",
            confidence=0.8,
            validated=validated,
        )

    def test_unvalidated_conversation_cannot_enter_dataset(self):
        with self.assertRaises(PermissionError):
            validate_learning_example(self._example(False))

    def test_validated_examples_are_appended(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "train.jsonl"
            self.assertEqual(append_validated_examples(path, [self._example()]), 1)
            self.assertEqual(path.read_text(encoding="utf-8").count("candidate-1"), 1)

    def test_corrected_example_requires_correction(self):
        example = self._example()
        invalid = LearningExample(example.example_id, example.input, example.target, "corrected", validated=True)
        with self.assertRaises(ValueError):
            validate_learning_example(invalid)

    def test_history_and_regression_are_recorded(self):
        baseline = TrainingReport("1.0.0", 18, 6, None, {"intent": {"f1_macro": 0.5}}, "ok")
        candidate = TrainingReport("1.1.0", 24, 6, None, {"intent": {"f1_macro": 0.4}}, "ok")
        with tempfile.TemporaryDirectory() as directory:
            registry = EvolutionRegistry(Path(directory) / "history.json")
            registry.record(baseline)
            comparison = registry.compare(baseline, candidate)
            self.assertTrue(comparison.regression)
            with self.assertRaises(ValueError):
                registry.activate(comparison, approved=True)

    def test_activation_requires_explicit_approval(self):
        baseline = TrainingReport("1.0.0", 18, 6, None, {"intent": {"f1_macro": 0.5}}, "ok")
        candidate = TrainingReport("1.1.0", 24, 6, None, {"intent": {"f1_macro": 0.6}}, "ok")
        registry = EvolutionRegistry(Path(tempfile.mkdtemp()) / "history.json")
        comparison = registry.compare(baseline, candidate)
        with self.assertRaises(PermissionError):
            registry.activate(comparison)
        self.assertTrue(registry.activate(comparison, approved=True).approved)


if __name__ == "__main__":
    unittest.main()
