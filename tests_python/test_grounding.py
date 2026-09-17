import unittest

from nexus_ai.grounding import GroundingValidator
from nexus_ai.responses import NexusResponseGenerator, ResponseInput


class GroundingTests(unittest.TestCase):
    def setUp(self):
        self.validator = GroundingValidator()
        self.interpretation = {
            "action": "query",
            "intent": "query_bug",
            "requires_clarification": False,
        }

    def test_existing_data_is_grounded_and_associated(self):
        result = self.validator.validate(
            self.interpretation,
            {},
            [{"ok": True, "data": [{"id": 7}], "meta": {"entity": "bug", "tool": "devcontrol.bugs.list"}}],
        )

        self.assertTrue(result.grounded)
        self.assertEqual(result.evidence[0]["record_ids"], [7])

    def test_missing_data_cannot_be_invented(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "Muéstrame los bugs abiertos.",
            interpretation=self.interpretation,
        ))

        self.assertNotEqual(result.status, "answered")
        self.assertIn("evidencia verificable", result.text)

    def test_empty_successful_result_is_explicit_absence(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "Muéstrame los bugs abiertos.",
            interpretation=self.interpretation,
            tool_results=[{
                "ok": True,
                "data": [],
                "meta": {"entity": "bug", "tool": "devcontrol.bugs.list"},
            }],
        ))

        self.assertEqual(result.status, "answered")
        self.assertIn("No se encontraron registros", result.text)

    def test_multiple_results_remain_evidence(self):
        result = self.validator.validate(
            self.interpretation,
            {},
            [{"ok": True, "data": [{"id": 1}, {"id": 2}], "meta": {"entity": "bug"}}],
        )

        self.assertTrue(result.grounded)
        self.assertEqual(result.evidence[0]["record_count"], 2)

    def test_ambiguous_query_requires_clarification(self):
        result = self.validator.validate(
            {**self.interpretation, "requires_clarification": True},
            {},
            [],
        )

        self.assertFalse(result.grounded)
        self.assertTrue(result.ambiguous)
        self.assertTrue(result.missing)


if __name__ == "__main__":
    unittest.main()
