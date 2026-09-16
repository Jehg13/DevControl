import unittest

from nexus_ai.responses import NexusResponseGenerator, ResponseInput


class NexusResponseTests(unittest.TestCase):
    def test_conversational_response_keeps_facts_and_inferences_separate(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "¿Qué problemas importantes existen?",
            reasoning={"findings": [
                {"finding_type": "fact", "statement": "El bug 1 está abierto."},
                {"finding_type": "inference", "statement": "Requiere atención prioritaria."},
            ]},
            conversation=[{"role": "user", "content": "Revisa el proyecto."}],
        ))
        self.assertEqual(result.status, "answered")
        self.assertIn("Información confirmada", result.text)
        self.assertIn("Inferencias", result.text)
        self.assertEqual(len(result.conversation), 3)

    def test_plan_is_proposal_not_execution(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "Prepara el despliegue.", plan=[{"number": 1, "objective": "Revisar pruebas"}],
        ))
        self.assertEqual(result.status, "plan_proposed")
        self.assertNotIn("Acción ejecutada por Laravel", result.text)
        self.assertFalse(result.verified)

    def test_pending_action_is_not_claimed_as_executed(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "Actualiza el bug.", execution_status="confirmation_required",
            tool_results=[{"ok": False, "error": {"code": "confirmation_required", "message": "Confirma primero."}}],
        ))
        self.assertEqual(result.status, "action_failed")
        self.assertNotIn("realizada", result.text)

    def test_only_successful_laravel_result_is_execution(self):
        result = NexusResponseGenerator().generate(ResponseInput(
            "Lista los bugs.", tool_results=[{"ok": True, "data": [{"id": 1}]}],
        ))
        self.assertEqual(result.status, "action_executed")
        self.assertIn("Acción ejecutada por Laravel", result.text)
        self.assertTrue(result.verified)

    def test_missing_information_is_explicit(self):
        result = NexusResponseGenerator().generate(ResponseInput("Revisa este proyecto"))
        self.assertEqual(result.status, "missing_information")
        self.assertIn("Información faltante", result.text)


if __name__ == "__main__":
    unittest.main()
