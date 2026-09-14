import json
import tempfile
import unittest
from pathlib import Path

from nexus_tokenizer import NexusTokenizer
from nexus_tool_calling import (
    ToolCall,
    ToolCallValidator,
    ToolCallingDataset,
    ToolResult,
    evaluate_tool_calls,
)


class NexusToolCallingTest(unittest.TestCase):
    def setUp(self):
        self.tokenizer = NexusTokenizer.train(
            ["<|tool_call|> search_file parameters reason expected_result"],
            vocab_size=320,
            min_frequency=1,
        )
        self.valid = {
            "tool": "search_file",
            "parameters": {"path": "app", "query": "Nexus"},
            "reason": "Find Nexus code.",
            "expected_result": "Matching files.",
        }

    def test_validator_does_not_execute_and_checks_permissions(self):
        validator = ToolCallValidator()
        self.assertIsInstance(validator.validate(self.valid), ToolCall)
        blocked = validator.validate(self.valid, set())
        self.assertIsInstance(blocked, ToolResult)
        self.assertEqual(blocked.status, "permission_required")
        unknown = dict(self.valid, tool="invented_tool")
        self.assertEqual(validator.validate(unknown).status, "invalid")

    def test_execution_is_after_validation_and_permission(self):
        calls = []
        result = ToolCallValidator().authorize_and_execute(
            self.valid,
            {"nexus.tool.search_file"},
            lambda tool, params: calls.append((tool, params)) or ["app/Nexus.php"],
        )
        self.assertEqual(result.status, "completed")
        self.assertEqual(calls[0][0], "search_file")

    def test_dataset_rejects_invalid_calls(self):
        records = [
            {"id": "valid", "prompt": "Find code.", "tool_call": self.valid},
            {"id": "unknown", "prompt": "Do magic.", "tool_call": dict(self.valid, tool="magic")},
        ]
        with tempfile.TemporaryDirectory() as directory:
            stats = ToolCallingDataset(self.tokenizer).build(records, directory)
            self.assertEqual(stats["accepted"], 1)
            self.assertEqual(stats["rejected"]["unknown tool"], 1)
            self.assertTrue((Path(directory) / "training-tool-calling.jsonl").exists())

    def test_benchmark_measures_invalid_calls(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "benchmark.json"
            path.write_text(json.dumps([{
                "id": "unknown",
                "call": dict(self.valid, tool="invented_tool"),
                "permissions": [],
                "expected_status": "invalid",
            }]), encoding="utf-8")
            report = evaluate_tool_calls(path)
            self.assertEqual(report["accuracy"], 1.0)


if __name__ == "__main__":
    unittest.main()
