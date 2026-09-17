import json
import tempfile
import unittest
from pathlib import Path

from nexus_ai.datasets.validator import DatasetValidationError, validate_dataset_directory


DATASET_DIR = Path(__file__).parents[1] / "nexus_ai" / "datasets"


class DatasetValidationTests(unittest.TestCase):
    def test_checked_in_datasets_are_valid(self):
        result = validate_dataset_directory(DATASET_DIR)

        self.assertEqual(result["records"], 30)
        self.assertEqual(result["files"], 2)

    def test_missing_fields_are_reported(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "invalid.jsonl"
            path.write_text(json.dumps({"id": "broken"}) + "\n", encoding="utf-8")

            with self.assertRaises(DatasetValidationError) as raised:
                validate_dataset_directory(directory)

            self.assertIn("missing field: input", str(raised.exception))

    def test_duplicate_ids_and_inputs_are_reported(self):
        record = {
            "id": "duplicate",
            "version": "1.0.0",
            "split": "train",
            "input": "Consulta repetida",
            "target": {
                "intent": "consultar_proyectos",
                "entity": "proyecto",
                "action": "query",
                "entities": {},
                "filters": {},
                "content": "",
                "needs_clarification": False,
            },
        }
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "duplicates.jsonl"
            path.write_text(json.dumps(record) + "\n" + json.dumps(record) + "\n", encoding="utf-8")

            with self.assertRaises(DatasetValidationError) as raised:
                validate_dataset_directory(directory)

            self.assertIn("duplicate id", str(raised.exception))
            self.assertIn("duplicate input", str(raised.exception))

    def test_inconsistent_labels_are_reported(self):
        record = {
            "id": "inconsistent",
            "version": "1.0.0",
            "split": "train",
            "input": "Borra el bug BUG-001.",
            "target": {
                "intent": "eliminar_bug",
                "entity": "bug",
                "action": "clarify",
                "entities": {"id": "BUG-001"},
                "filters": {},
                "content": "",
                "needs_clarification": False,
            },
        }
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "inconsistent.jsonl"
            path.write_text(json.dumps(record) + "\n", encoding="utf-8")

            with self.assertRaises(DatasetValidationError) as raised:
                validate_dataset_directory(directory)

            self.assertIn("clarify actions must require clarification", str(raised.exception))
