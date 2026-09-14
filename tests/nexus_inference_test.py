import json
import tempfile
import unittest
from pathlib import Path

from nexus_inference import InferenceCancelled, InferenceConfig, LocalInferenceRuntime
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusInferenceTest(unittest.TestCase):
    def _runtime(self, directory: str) -> LocalInferenceRuntime:
        tokenizer = NexusTokenizer.train(["local Nexus response"], vocab_size=300, min_frequency=1)
        tokenizer_path = Path(directory) / "tokenizer.json"
        tokenizer.save(tokenizer_path)
        model = NexusMicroModel(tokenizer.vocab_size, 4)
        checkpoint = Path(directory) / "latest.json"
        checkpoint.write_text(json.dumps({
            "format": "nexus-micro-checkpoint-v1",
            "step": 0,
            "epoch": 0,
            "model": model.to_dict(),
            "dataset_sha256": "test",
        }), encoding="utf-8")
        return LocalInferenceRuntime(
            checkpoint,
            tokenizer_path,
            InferenceConfig(max_context_tokens=8, max_new_tokens=4, temperature=0, timeout_seconds=5),
        )

    def test_loads_artifacts_and_respects_generation_limits(self):
        with tempfile.TemporaryDirectory() as directory:
            result = self._runtime(directory).generate("local Nexus response")
            self.assertLessEqual(result["prompt_tokens"], 8)
            self.assertLessEqual(result["completion_tokens"], 4)
            self.assertIn("tokens_per_second", result)

    def test_streams_tokens_and_can_cancel(self):
        with tempfile.TemporaryDirectory() as directory:
            runtime = self._runtime(directory)
            tokens = []
            with self.assertRaises(InferenceCancelled):
                runtime.generate("local", cancel=lambda: len(tokens) >= 1, on_token=tokens.append)

    def test_int8_mode_is_optional_and_keeps_generation_bounded(self):
        with tempfile.TemporaryDirectory() as directory:
            tokenizer = NexusTokenizer.train(["local Nexus response"], vocab_size=300, min_frequency=1)
            tokenizer_path = Path(directory) / "tokenizer.json"
            tokenizer.save(tokenizer_path)
            model = NexusMicroModel(tokenizer.vocab_size, 4)
            checkpoint = Path(directory) / "latest.json"
            checkpoint.write_text(json.dumps({
                "format": "nexus-micro-checkpoint-v1",
                "step": 0,
                "epoch": 0,
                "model": model.to_dict(),
                "dataset_sha256": "test",
            }), encoding="utf-8")
            runtime = LocalInferenceRuntime(
                checkpoint,
                tokenizer_path,
                InferenceConfig(max_new_tokens=2, temperature=0, quantization="int8"),
            )
            result = runtime.generate("local")
            self.assertEqual(result["quantization"], "int8")
            self.assertLessEqual(result["completion_tokens"], 2)


if __name__ == "__main__":
    unittest.main()
