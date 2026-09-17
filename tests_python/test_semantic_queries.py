import unittest

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.nlp import NexusNlpInterpreter
from nexus_ai.semantic import SemanticQueryBuilder


class SemanticQueryTests(unittest.TestCase):
    def setUp(self):
        self.interpreter = NexusNlpInterpreter()
        self.builder = SemanticQueryBuilder()

    def test_each_supported_entity_generates_read_only_query(self):
        messages = {
            "proyecto": "¿Cuántos proyectos tengo?",
            "tarea": "Muéstrame las tareas pendientes.",
            "bug": "Muéstrame los bugs abiertos.",
            "incidente": "¿Qué incidentes están pendientes?",
            "actualizacion": "Lista las actualizaciones.",
            "usuario": "¿Cuántos usuarios existen?",
        }
        for entity, message in messages.items():
            query = self.builder.build(self.interpreter.interpret(message).to_dict())
            self.assertIsNotNone(query)
            self.assertEqual(query.entity, entity)
            self.assertFalse(query.executable)
            self.assertEqual(query.permission_scope, "devcontrol.read")

    def test_operations_and_grouping_are_structured(self):
        for message, operation in (
            ("¿Cuántos bugs existen?", "count"),
            ("¿Qué proyecto tiene más bugs?", "compare"),
            ("Agrupa las tareas por estado.", "group"),
            ("Dame estadísticas de incidentes.", "statistics"),
        ):
            query = self.builder.build(self.interpreter.interpret(message).to_dict())
            self.assertEqual(query.operation, operation)
        grouped = self.builder.build(
            self.interpreter.interpret("Agrupa las tareas por estado.").to_dict()
        )
        self.assertEqual(grouped.group_by, ["status"])

    def test_unknown_fields_and_entities_are_not_forwarded(self):
        query = self.builder.build({
            "action": "query",
            "entity": "bug",
            "operation": "list",
            "filters": {"sql": "drop table", "status": "Abierto"},
            "sort": {"field": "unknown", "direction": "desc"},
            "limit": 1000,
        })
        self.assertEqual(query.filters, {"status": "Abierto"})
        self.assertEqual(query.sort, {})
        self.assertIsNone(query.limit)

    def test_ambiguous_or_non_query_does_not_create_request(self):
        self.assertIsNone(self.builder.build({
            "action": "query", "entity": "bug", "requires_clarification": True,
        }))
        self.assertIsNone(self.builder.build({"action": "delete", "entity": "bug"}))

    def test_application_emits_semantic_contract_without_execution_flag(self):
        response = NexusAiApplication().handle(
            __import__("nexus_ai.api.contracts", fromlist=["NexusAiRequest"]).NexusAiRequest(
                "¿Cuántas tareas pendientes hay?"
            )
        )
        query = response.data_requests[0]
        self.assertEqual(query["entity"], "tarea")
        self.assertEqual(query["operation"], "count")
        self.assertFalse(query["executable"])
        self.assertEqual(query["permission_scope"], "devcontrol.read")


if __name__ == "__main__":
    unittest.main()
