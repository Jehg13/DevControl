import unittest

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.api.contracts import ConversationTurn, NexusAiRequest, NexusContext, ToolDescriptor


class NexusApplicationPipelineTests(unittest.TestCase):
    def test_real_read_conversation_uses_nlp_knowledge_reasoning_and_response(self):
        response = NexusAiApplication().process(
            NexusAiRequest(
                "¿Qué bugs abiertos existen?",
                context=NexusContext(values={"project_id": 1}),
                conversation=[ConversationTurn("user", "Revisa DevControl.")],
                permissions=["nexus.read"],
                tools=[ToolDescriptor("bug.list", "Lista bugs", permissions=["nexus.read"])],
            ),
            recovered_data={"bug": [{"id": 4, "status": "abierto", "priority": "critica"}]},
            knowledge_data={"proyecto": [{"id": 1, "name": "DevControl"}], "bug": [{"id": 4, "project_id": 1, "status": "abierto"}]},
            session_id="e2e-read",
        )

        self.assertEqual(response.status, "answered")
        self.assertEqual(response.interpretation.intent, "query_bug")
        self.assertIn("Información confirmada", response.response)
        self.assertEqual(response.tool_information[0]["name"], "bug.list")
        self.assertFalse(response.to_dict()["errors"])

    def test_write_conversation_produces_plan_without_claiming_execution(self):
        response = NexusAiApplication().process(
            NexusAiRequest(
                "Quiero preparar este proyecto para desplegarlo.",
                permissions=["nexus.read"],
                tools=[ToolDescriptor("project.inspect", "Inspecciona proyecto", permissions=["nexus.read"])],
            ),
            recovered_data={"project": [{"id": 1}]},
            session_id="e2e-plan",
        )

        self.assertTrue(response.plan)
        self.assertIn(response.status, {"plan_proposed", "action_pending", "missing_information"})
        self.assertNotIn("Acción ejecutada por Laravel", response.response)

    def test_successful_laravel_result_is_the_only_execution_claim(self):
        request = NexusAiRequest(
            "Muestra los bugs.",
            permissions=["nexus.read"],
            tools=[ToolDescriptor("bug.list", "Lista bugs", permissions=["nexus.read"])],
        )
        response = NexusAiApplication().process(
            request,
            recovered_data={"bug": [{"id": 4}]},
            execution_results=[{"ok": True, "data": [{"id": 4}]}],
        )

        self.assertEqual(response.status, "action_executed")
        self.assertIn("Acción ejecutada por Laravel", response.response)

    def test_failed_laravel_result_remains_a_failure(self):
        response = NexusAiApplication().process(
            NexusAiRequest("Actualiza el bug.", permissions=["nexus.write"]),
            execution_results=[{"ok": False, "error": {"code": "permission_denied", "message": "Sin permiso."}}],
        )

        self.assertEqual(response.status, "action_failed")
        self.assertIn("Sin permiso.", response.response)
        self.assertNotIn("se considera realizada", response.response)


if __name__ == "__main__":
    unittest.main()
