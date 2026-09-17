import tempfile
import unittest
from pathlib import Path

from nexus_training.scalable import (
    ArchitectureConfig,
    SMALL_ARCHITECTURE,
    ScalableCausalModel,
    load_checkpoint,
    save_checkpoint,
)


class ScalableModelTest(unittest.TestCase):
    def test_small_architecture_initializes_and_round_trips(self):
        model = ScalableCausalModel(SMALL_ARCHITECTURE)
        before = model.logits(3)
        loss = model.train_pair(3, 4, 0.01)
        after = model.logits(3)
        self.assertEqual(len(before), SMALL_ARCHITECTURE.vocab_size)
        self.assertEqual(len(after), SMALL_ARCHITECTURE.vocab_size)
        self.assertGreater(loss, 0)
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "small.json"
            save_checkpoint(path, model, {"test": True})
            restored = load_checkpoint(path)
            self.assertEqual(restored.to_dict(), model.to_dict())

    def test_invalid_head_dimensions_are_rejected(self):
        with self.assertRaises(ValueError):
            ArchitectureConfig("invalid", 512, 31, 2, 2, 64, 128).validate()


if __name__ == "__main__":
    unittest.main()
