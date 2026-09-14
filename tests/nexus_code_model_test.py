import json
import tempfile
import unittest
from pathlib import Path

from nexus_code_model import CodeExample, CodeSpecialization, evaluate_benchmark
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusCodeModelTest(unittest.TestCase):
    def setUp(self):
        self.tokenizer = NexusTokenizer.train(
            ["<|user|>PHP complete_code return", "<|assistant|>Route::get UserController"],
            vocab_size=320,
            min_frequency=1,
        )

    def test_builds_language_shards_and_rejects_invalid_examples(self):
        examples = [
            CodeExample("php", "PHP", "complete_code", "function add() {", "return 1; }", "MIT"),
            CodeExample("bad-language", "Rust", "complete_code", "fn main", "{}", "MIT"),
            CodeExample("bad-task", "PHP", "unknown", "x", "y", "MIT"),
        ]
        with tempfile.TemporaryDirectory() as directory:
            stats = CodeSpecialization(self.tokenizer).build(examples, directory)
            self.assertEqual(stats["accepted"], 1)
            self.assertTrue((Path(directory) / "training-php.jsonl").exists())
            self.assertEqual(stats["rejected"]["unsupported_language"], 1)

    def test_benchmark_reports_each_language_independently(self):
        model = NexusMicroModel(self.tokenizer.vocab_size, 8, seed=1)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "benchmark.json"
            path.write_text(json.dumps([{
                "id": "case", "language": "PHP", "task": "complete_code",
                "prompt": "return", "expected_contains": ["not-present"],
            }]), encoding="utf-8")
            report = evaluate_benchmark(model, self.tokenizer, path, generation_length=2)
            self.assertIn("PHP", report["language_scores"])
            self.assertEqual(report["cases"], 1)


if __name__ == "__main__":
    unittest.main()
