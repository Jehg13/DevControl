import unittest

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.api.contracts import (
    AuthenticatedUser,
    NexusAiRequest,
    NexusContext,
    ToolDescriptor,
)


class NexusAiFoundationTests(unittest.TestCase):
    def test_request_serializes_laravel_boundary_data(self):
        request = NexusAiRequest(
            message="Ejecuta las pruebas.",
            user=AuthenticatedUser(id=7, name="Admin", role="admin"),
            context=NexusContext(values={"route": "asistente.message"}),
            permissions=["nexus.read"],
            tools=[ToolDescriptor("test_execution", "Run tests", permissions=["nexus.read"])],
        )

        payload = request.to_dict()

        self.assertEqual(payload["message"], "Ejecuta las pruebas.")
        self.assertEqual(payload["user"]["role"], "admin")
        self.assertEqual(payload["tools"][0]["name"], "test_execution")

    def test_empty_message_is_rejected(self):
        with self.assertRaises(ValueError):
            NexusAiRequest(message=" ")

    def test_application_does_not_execute_tools_or_claim_intelligence(self):
        request = NexusAiRequest(
            message="Haz push de los cambios.",
            tools=[ToolDescriptor("git_push", "Push changes", permissions=["github.write"])],
        )

        response = NexusAiApplication().handle(request)

        self.assertEqual(response.status, "not_implemented")
        self.assertEqual(response.interpretation.intent, "unclassified")
        self.assertEqual(response.proposed_actions, [])
        self.assertEqual(response.tool_information[0]["name"], "git_push")
        self.assertIn("aún no está implementada", response.response)

    def test_response_is_serializable(self):
        response = NexusAiApplication().handle(NexusAiRequest(message="Consulta"))

        self.assertEqual(response.to_dict()["status"], "not_implemented")
        self.assertIn("interpretation", response.to_dict())

    def test_application_creates_non_executable_laravel_tool_proposal(self):
        request = NexusAiRequest(
            message="Muestra los proyectos.",
            tools=[ToolDescriptor("project.list", "List projects", permissions=["nexus.read"])],
        )

        proposal = NexusAiApplication().propose_tool(request, "project.list", {"limit": 10})

        self.assertEqual(proposal.tool_name, "project.list")
        self.assertEqual(proposal.operation, "read")
        self.assertFalse(proposal.executable)


if __name__ == "__main__":
    unittest.main()
