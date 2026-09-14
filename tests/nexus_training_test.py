import json
import tempfile
import unittest
from pathlib import Path

from nexus_training import TrainingConfig, train


class NexusTrainingTest(unittest.TestCase):
    def _dataset(self, directory: str) -> None:
        for split in ("training", "validation"):
            Path(directory, f"{split}-00000.jsonl").write_text(
                json.dumps({"input_ids": [1, 2, 3, 2, 3, 4]}) + "\n",
                encoding="utf-8",
            )

    def test_end_to_end_training_writes_metrics_and_checkpoint(self):
        with tempfile.TemporaryDirectory() as directory:
            dataset = Path(directory) / "dataset"
            output = Path(directory) / "run"
            dataset.mkdir()
            self._dataset(str(dataset))
            summary = train(
                dataset,
                output,
                vocab_size=8,
                config=TrainingConfig(epochs=2, eval_interval=1, checkpoint_interval=1),
            )
            self.assertEqual(summary["steps"], 2)
            self.assertTrue((output / "latest.json").exists())
            self.assertTrue((output / "summary.json").exists())
            self.assertGreater(len((output / "training-log.jsonl").read_text()), 0)

    def test_resume_rejects_a_different_dataset(self):
        with tempfile.TemporaryDirectory() as directory:
            dataset = Path(directory) / "dataset"
            output = Path(directory) / "run"
            other = Path(directory) / "other"
            dataset.mkdir()
            other.mkdir()
            self._dataset(str(dataset))
            self._dataset(str(other))
            Path(other, "training-00000.jsonl").write_text(
                json.dumps({"input_ids": [7, 7, 7]}) + "\n", encoding="utf-8"
            )
            train(dataset, output, vocab_size=8, config=TrainingConfig(epochs=1))
            with self.assertRaises(ValueError):
                train(other, output, vocab_size=8, config=TrainingConfig(epochs=2), resume=output / "latest.json")


if __name__ == "__main__":
    unittest.main()
