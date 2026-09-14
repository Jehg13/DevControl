import json
import tempfile
import unittest
from pathlib import Path

from nexus_runtime import NexusRuntime, RuntimeErrorBase
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusRuntimeTest(unittest.TestCase):
    def _runtime(self, directory: str) -> NexusRuntime:
        root = Path(directory)
        tokenizer = NexusTokenizer.train(["runtime response"], vocab_size=300, min_frequency=1)
        tokenizer.save(root / "tokenizer.json")
        model = NexusMicroModel(tokenizer.vocab_size, 4)
        (root / "latest.json").write_text(json.dumps({
            "format": "nexus-micro-checkpoint-v1",
            "step": 0,
            "epoch": 0,
            "model": model.to_dict(),
            "dataset_sha256": "test",
        }), encoding="utf-8")
        manifest = root / "config" / "nexus-runtime.json"
        manifest.parent.mkdir()
        manifest.write_text(json.dumps({
            "format": "nexus-runtime-manifest-v1",
            "versions": {
                "Nexus AI v9.9": {
                    "checkpoint": "latest.json",
                    "tokenizer": "tokenizer.json",
                    "max_new_tokens": 2,
                    "temperature": 0,
                    "tools": ["project.read"]
                }
            }
        }), encoding="utf-8")
        return NexusRuntime(manifest, "Nexus AI v9.9")

    def test_selects_exact_version_and_reports_health(self):
        with tempfile.TemporaryDirectory() as directory:
            runtime = self._runtime(directory)
            self.assertEqual(runtime.health()["version"], "Nexus AI v9.9")
            self.assertTrue(runtime.health()["healthy"])

    def test_records_metrics_memory_and_stream_events(self):
        with tempfile.TemporaryDirectory() as directory:
            events = []
            result = self._runtime(directory).infer("hello", tool_calls=[{"name": "project.read", "arguments": {}}], on_event=events.append)
            self.assertEqual(result["version"], "Nexus AI v9.9")
            self.assertIn("duration_ms", result["metrics"])
            self.assertGreaterEqual(len(events), 1)

    def test_rejects_unregistered_tools(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaises(RuntimeErrorBase):
                self._runtime(directory).infer("hello", tool_calls=[{"name": "dangerous.write", "arguments": {}}])


if __name__ == "__main__":
    unittest.main()
