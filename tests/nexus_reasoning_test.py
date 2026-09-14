import json
import tempfile
import unittest
from pathlib import Path

from nexus_reasoning import ReasoningExample, TechnicalReasoning, evaluate_reasoning
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusReasoningTest(unittest.TestCase):
    def setUp(self):
        self.tokenizer = NexusTokenizer.train(
            ["OBJECTIVE AVAILABLE MISSING HYPOTHESES STRATEGY ACTION VALIDATION uncertainty"],
            vocab_size=320,
            min_frequency=1,
        )

    def test_requires_structured_hypotheses_and_preserves_uncertainty(self):
        valid = ReasoningExample(
            "valid", "Analyze outage", ("log 500",), ("metrics",),
            ({"statement": "database issue", "status": "plausible", "confidence": 0.4, "evidence": ["log 500"]},),
            "collect metrics", ("collect metrics",), ("more evidence",), ("rerun test",), "medium",
        )
        invalid = ReasoningExample(
            "invalid", "Analyze outage", ("log 500",), (),
            ({"statement": "certain", "status": "fact", "confidence": 1.0, "evidence": []},),
            "act", (), (), (), "low",
        )
        with tempfile.TemporaryDirectory() as directory:
            stats = TechnicalReasoning(self.tokenizer).build([valid, invalid], directory)
            self.assertEqual(stats["accepted"], 1)
            self.assertEqual(stats["rejected"]["invalid_hypothesis"], 1)
            self.assertEqual(stats["hypothesis_statuses"]["plausible"], 1)

    def test_benchmark_reports_stage_coverage_and_fact_violations(self):
        model = NexusMicroModel(self.tokenizer.vocab_size, 8, seed=3)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "benchmark.json"
            path.write_text(json.dumps([{
                "id": "case", "prompt": "reason",
                "expected": {"objective": ["objective"]},
                "uncertainty_terms": ["unknown"],
                "forbidden_as_fact": ["definitely"],
            }]), encoding="utf-8")
            report = evaluate_reasoning(model, self.tokenizer, path, generation_length=2)
            self.assertIn("stage_scores", report)
            self.assertIn("fact_violation_rate", report)
            self.assertEqual(report["cases"], 1)


if __name__ == "__main__":
    unittest.main()
