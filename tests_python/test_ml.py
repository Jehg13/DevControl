import tempfile
import unittest
from pathlib import Path

from nexus_ai.ml.pipeline import NexusMlPipeline


ROOT = Path(__file__).parents[1]
TRAIN = ROOT / "nexus_ai" / "datasets" / "devcontrol_v1_train.jsonl"
VALIDATION = ROOT / "nexus_ai" / "datasets" / "devcontrol_v1_validation.jsonl"


class NexusMlTests(unittest.TestCase):
    def test_training_reports_real_validation_metrics_and_missing_test_split(self):
        report = NexusMlPipeline().train(TRAIN, VALIDATION)

        self.assertEqual(report.train_samples, 18)
        self.assertEqual(report.validation_samples, 6)
        self.assertIsNone(report.test_samples)
        self.assertIn("intent", report.metrics)
        self.assertIn("f1_macro", report.metrics["intent"])
        self.assertIn("confusion_matrix", report.metrics["intent"])
        self.assertIn("not_available", report.test_status)

    def test_model_can_be_saved_loaded_and_used_for_inference(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "nexus_ml.json"
            pipeline = NexusMlPipeline()
            pipeline.train(TRAIN, VALIDATION, model_path=path)

            loaded = NexusMlPipeline()
            loaded.load(path)
            prediction = loaded.predict("¿Qué bugs siguen abiertos?")

            self.assertIn("intent", prediction)
            self.assertIn("action", prediction)
            self.assertFalse(prediction["executable"])

    def test_model_does_not_execute_tools(self):
        pipeline = NexusMlPipeline()
        pipeline.train(TRAIN, VALIDATION)

        prediction = pipeline.predict("Elimina el bug BUG-001")

        self.assertFalse(prediction["executable"])


if __name__ == "__main__":
    unittest.main()
