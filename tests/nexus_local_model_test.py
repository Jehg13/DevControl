import json
import tempfile
import unittest
from pathlib import Path

from nexus_ai.models import InferenceEngine, ModelLoader, ModelLoadError
from nexus_inference import InferenceConfig
from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


class NexusLocalModelTest(unittest.TestCase):
    def _artifacts(self, root: Path) -> tuple[Path, Path]:
        tokenizer_path = root / "tokenizer.json"
        tokenizer = NexusTokenizer.train(["Nexus local response"], vocab_size=300, min_frequency=1)
        tokenizer.save(tokenizer_path)
        model = NexusMicroModel(tokenizer.vocab_size, 4)
        checkpoint = root / "latest.json"
        checkpoint.write_text(json.dumps({
            "format": "nexus-micro-checkpoint-v1",
            "step": 0,
            "epoch": 0,
            "model": model.to_dict(),
            "dataset_sha256": "test",
        }), encoding="utf-8")
        return checkpoint, tokenizer_path

    def test_missing_artifacts_are_structured(self):
        with tempfile.TemporaryDirectory() as directory:
            with self.assertRaisesRegex(ModelLoadError, "modelo local"):
                ModelLoader(Path(directory) / "missing.json", Path(directory) / "tokenizer.json").load()

    def test_loads_once_and_generates_structured_result(self):
        with tempfile.TemporaryDirectory() as directory:
            checkpoint, tokenizer = self._artifacts(Path(directory))
            loader = ModelLoader(checkpoint, tokenizer, InferenceConfig(max_new_tokens=2, temperature=0))
            first = loader.load()
            self.assertIs(first, loader.load())
            result = InferenceEngine(first).generate("Hola Nexus", {"source": "test"})
            self.assertIsInstance(result["text"], str)
            self.assertIn("completion_tokens", result)

    def test_rejects_invalid_vocabularies(self):
        with tempfile.TemporaryDirectory() as directory:
            checkpoint, tokenizer = self._artifacts(Path(directory))
            payload = json.loads(checkpoint.read_text(encoding="utf-8"))
            payload["model"]["vocab_size"] += 1
            checkpoint.write_text(json.dumps(payload), encoding="utf-8")
            with self.assertRaisesRegex(ModelLoadError, "vocabularios"):
                ModelLoader(checkpoint, tokenizer).load()


if __name__ == "__main__":
    unittest.main()
