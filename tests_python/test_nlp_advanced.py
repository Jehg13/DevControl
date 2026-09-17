import unittest

from nexus_ai.nlp import NexusNlpInterpreter


class AdvancedNlpTests(unittest.TestCase):
    def setUp(self):
        self.interpreter = NexusNlpInterpreter()

    def test_extracts_structured_entities_filters_and_constraints(self):
        result = self.interpreter.interpret(
            "Muéstrame únicamente los bugs críticos abiertos del proyecto DevControl "
            "que no estén solucionados."
        )

        self.assertEqual(result.entity, "bug")
        self.assertEqual(result.filters, {
            "priority": "Alta",
            "status": "Abierto",
            "project_name": "devcontrol",
        })
        self.assertIn("proyecto", result.related_entities)
        self.assertTrue(result.negations)
        self.assertTrue(result.constraints["exclusive"])
        self.assertFalse(result.executable)

    def test_extracts_dates_quantities_and_comparison(self):
        result = self.interpreter.interpret(
            "¿Cuál proyecto tiene más bugs desde 12/09/2026? Muéstrame los primeros 3."
        )

        self.assertEqual(result.operation, "compare")
        self.assertEqual(result.entities["quantity"], 3)
        self.assertTrue(result.entities["comparison"])
        self.assertEqual(result.limit, 3)
        self.assertTrue(any(item["normalized"] == "12/09/2026" for item in result.temporal_references))

    def test_follow_up_inherits_entity_and_filters_from_context(self):
        context = {
            "recent_turns": [{
                "role": "assistant",
                "text": "Hay bugs abiertos.",
                "interpretation": {
                    "entity": "bug",
                    "filters": {"status": "Abierto"},
                    "entities": {},
                },
            }],
        }

        result = self.interpreter.interpret("¿Y cuáles?", context)

        self.assertEqual(result.entity, "bug")
        self.assertEqual(result.filters["status"], "Abierto")
        self.assertTrue(result.requires_context)
        self.assertEqual(result.inherited_context["entity"], "bug")

    def test_related_project_is_not_mistaken_for_primary_entity(self):
        context = {
            "recent_turns": [{
                "role": "assistant",
                "text": "Encontré bugs.",
                "interpretation": {"entity": "bug", "filters": {}, "entities": {}},
            }],
        }

        result = self.interpreter.interpret(
            "¿Y cuáles pertenecen al proyecto anterior?", context
        )

        self.assertEqual(result.entity, "bug")
        self.assertIn("proyecto", result.related_entities)
        self.assertTrue(result.requires_context)

    def test_ambiguous_follow_up_without_context_requests_clarification(self):
        result = self.interpreter.interpret("¿Cuál tiene más?")

        self.assertTrue(result.requires_clarification)
        self.assertEqual(result.ambiguity_level, "high")
        self.assertIsNotNone(result.clarification_question)


if __name__ == "__main__":
    unittest.main()
