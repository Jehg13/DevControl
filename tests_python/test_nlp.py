import unittest

from nexus_ai.nlp import NexusNlpInterpreter


class NexusNlpTests(unittest.TestCase):
    def setUp(self):
        self.interpreter = NexusNlpInterpreter()

    def test_direct_bug_query_extracts_open_filter(self):
        result = self.interpreter.interpret("Muéstrame los bugs abiertos")
        self.assertEqual(result.intent, "query_bug")
        self.assertEqual(result.filters["status"], "Abierto")
        self.assertFalse(result.executable)

    def test_natural_synonyms_map_to_same_bug_intent(self):
        intents = {
            self.interpreter.interpret(text).intent
            for text in (
                "Muéstrame los bugs abiertos",
                "¿Qué errores siguen pendientes?",
                "Enséñame los problemas que todavía no solucionamos",
            )
        }
        self.assertEqual(intents, {"query_bug"})

    def test_reasonable_typo_is_normalized(self):
        result = self.interpreter.interpret("Muéstrame los buggs abiertos")
        self.assertEqual(result.intent, "query_bug")
        self.assertEqual(result.normalized_text, "muéstrame los bugs abiertos")

    def test_delete_demonstrative_requires_context_and_confirmation(self):
        result = self.interpreter.interpret("Elimina ese bug")
        self.assertEqual(result.intent, "delete_bug")
        self.assertTrue(result.requires_context)
        self.assertTrue(result.requires_clarification)
        self.assertTrue(result.requires_confirmation)
        self.assertFalse(result.executable)

    def test_incomplete_request_requires_entity_clarification(self):
        result = self.interpreter.interpret("Quiero cambiar algo")
        self.assertTrue(result.incomplete)
        self.assertTrue(result.requires_clarification)
        self.assertEqual(result.intent, "solicitar_aclaracion")

    def test_temporal_priority_and_status_filters(self):
        result = self.interpreter.interpret("¿Qué bugs críticos abiertos hay esta semana?")
        self.assertEqual(result.filters, {"priority": "Alta", "status": "Abierto"})
        self.assertEqual(result.temporal_references[0]["normalized"], "this_week")

    def test_multiple_entities_and_path_are_preserved(self):
        result = self.interpreter.interpret(
            "Analiza el bug BUG-004 en app/Http/Controllers/ProyectoController.php"
        )
        self.assertEqual(result.entity, "bug")
        self.assertEqual(result.entities["id"], "BUG-004")
        self.assertEqual(result.entities["path"], "app/Http/Controllers/ProyectoController.php")
        self.assertEqual(result.action, "analyze")

    def test_follow_up_reference_is_structured(self):
        result = self.interpreter.interpret("¿Y el anterior?")
        self.assertTrue(result.requires_context)
        self.assertTrue(result.ambiguous)
        self.assertTrue(result.requires_clarification)
        self.assertEqual(result.contextual_references[0]["kind"], "previous_result")


if __name__ == "__main__":
    unittest.main()
