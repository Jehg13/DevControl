import unittest

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.api.contracts import NexusAiRequest
from nexus_ai.nlp import NexusNlpInterpreter


class DataIntelligenceNlpTests(unittest.TestCase):
    def setUp(self):
        self.interpreter = NexusNlpInterpreter()

    def assert_query(self, message, entity, operation, status=None):
        result = self.interpreter.interpret(message)
        self.assertEqual(result.entity, entity)
        self.assertEqual(result.action, "query")
        self.assertEqual(result.operation, operation)
        if status:
            self.assertEqual(result.filters["status"], status)
        self.assertFalse(result.requires_clarification)

    def test_projects_count_and_names(self):
        self.assert_query("¿Cuántos proyectos tengo?", "proyecto", "count")
        self.assert_query("¿Cómo se llaman mis proyectos?", "proyecto", "list")

    def test_task_filters_are_not_incidents(self):
        self.assert_query("¿Cuántas tareas pendientes hay en DevControl?", "tarea", "count", "Pendiente")
        self.assert_query("¿Cuántos incidentes existen y cuáles están pendientes?", "incidente", "count", "Pendiente")

    def test_bug_synonyms_and_priority(self):
        self.assert_query("Muéstrame los bugs abiertos.", "bug", "list", "Abierto")
        self.assert_query("¿Qué errores críticos hay?", "bug", "list")
        self.assertEqual(self.interpreter.interpret("¿Qué errores críticos hay?").filters["priority"], "Alta")

    def test_project_comparisons_and_review_priority(self):
        result = self.interpreter.interpret("¿Qué proyecto tiene más bugs?")
        self.assertEqual(result.entity, "proyecto")
        self.assertEqual(result.operation, "compare")
        self.assertEqual(result.sort["field"], "bugs_count")
        result = self.interpreter.interpret("¿Qué tareas deberían revisarse primero?")
        self.assertEqual(result.entity, "tarea")
        self.assertEqual(result.sort["field"], "priority")

    def test_application_requests_authorized_data_instead_of_source_code_analysis(self):
        response = NexusAiApplication().handle(
            NexusAiRequest("¿Cuántos bugs abiertos existen?")
        )

        self.assertEqual(response.interpretation.intent, "query_bug")
        self.assertEqual(response.data_requests[0]["entity"], "bug")
        self.assertEqual(response.data_requests[0]["operation"], "count")
        self.assertEqual(response.data_requests[0]["filters"]["status"], "Abierto")
        self.assertFalse(response.to_dict()["data_requests"][0].get("executable", False))


if __name__ == "__main__":
    unittest.main()
