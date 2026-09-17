import unittest

from nexus_reasoning.investigation import TechnicalInvestigationEngine


class TechnicalInvestigationTest(unittest.TestCase):
    def setUp(self):
        self.engine = TechnicalInvestigationEngine()

    def test_different_problems_produce_different_objectives(self):
        first = self.engine.build("El bug de sincronización falla al guardar")
        second = self.engine.build("El incidente de latencia provoca timeouts")
        self.assertEqual(first.status, "planned")
        self.assertIn("sincronización", first.objective)
        self.assertIn("latencia", second.objective)
        self.assertNotEqual(first.objective, second.objective)

    def test_generic_request_requires_clarification(self):
        result = self.engine.build("Ayúdame con esto")
        self.assertEqual(result.status, "needs_clarification")
        self.assertFalse(result.executable)
        self.assertIn("technical_objective", result.missing_information)

    def test_evidence_keeps_origin_query_result_and_timestamp(self):
        result = self.engine.build(
            "La excepción del servicio falla al procesar solicitudes",
            evidence=[{
                "origin": "authorized_tool",
                "tool": "logs.search",
                "query": {"evidence_type": "error_record", "term": "exception"},
                "result": {"count": 2},
                "timestamp": "2024-01-01T00:00:00Z",
                "relevance": 0.9,
            }],
        )
        self.assertEqual(result.status, "incomplete")
        self.assertEqual(result.evidence[0].origin, "authorized_tool")
        self.assertEqual(result.evidence[0].tool, "logs.search")
        self.assertEqual(result.evidence[0].result, {"count": 2})
        self.assertIn("observed_behavior", result.missing_information)

    def test_complete_evidence_supports_conclusion_without_execution(self):
        evidence = [
            {"origin": "tool", "tool": "diagnostics", "query": {"evidence_type": kind},
             "result": {"present": True}, "relevance": 1.0}
            for kind in ("observed_behavior", "reproduction_or_event", "error_record")
        ]
        result = self.engine.build("El bug de prioridad alta sigue abierto", evidence=evidence)
        self.assertEqual(result.status, "analyzed")
        self.assertEqual(result.validation[0]["status"], "supported")
        self.assertFalse(result.executable)


if __name__ == "__main__":
    unittest.main()
