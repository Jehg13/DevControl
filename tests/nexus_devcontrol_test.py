import json
import tempfile
import unittest
from pathlib import Path

from nexus_devcontrol import DevControlExample, DevControlSpecialization, evaluate_devcontrol
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusDevControlTest(unittest.TestCase):
    def setUp(self):
        self.tokenizer = NexusTokenizer.train(
            ["DevControl project evidence repository logs", "No tengo evidencia suficiente."],
            vocab_size=320,
            min_frequency=1,
        )

    def test_builds_grounded_dataset_and_requires_abstention(self):
        examples = [
            DevControlExample(
                "supported", "analyze_project", "Analyze project", ("repository exists",),
                "Evidence shows the repository exists.", "supported", ("projects", "repositories"),
            ),
            DevControlExample(
                "insufficient", "probable_cause", "Find cause", (),
                "No tengo evidencia suficiente.", "insufficient_evidence", ("incidents",),
            ),
            DevControlExample(
                "unsafe", "probable_cause", "Find cause", (),
                "The database is broken.", "insufficient_evidence", ("incidents",),
            ),
        ]
        with tempfile.TemporaryDirectory() as directory:
            stats = DevControlSpecialization(self.tokenizer).build(examples, directory)
            self.assertEqual(stats["accepted"], 2)
            self.assertEqual(stats["abstention_examples"], 1)
            self.assertEqual(stats["rejected"]["missing_abstention"], 1)

    def test_benchmark_measures_abstention_separately(self):
        model = NexusMicroModel(self.tokenizer.vocab_size, 8, seed=2)
        with tempfile.TemporaryDirectory() as directory:
            benchmark = Path(directory) / "benchmark.json"
            benchmark.write_text(json.dumps([{
                "id": "missing", "task": "probable_cause",
                "prompt": "No logs", "expected_status": "insufficient_evidence",
                "expected_contains": ["evidencia"],
            }]), encoding="utf-8")
            report = evaluate_devcontrol(model, self.tokenizer, benchmark, generation_length=2)
            self.assertIn("status_accuracy", report)
            self.assertEqual(report["cases"], 1)


if __name__ == "__main__":
    unittest.main()
