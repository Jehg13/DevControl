import unittest

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.reasoning import NexusReasoner, ReasoningRequest


class NexusReasoningTests(unittest.TestCase):
    def test_reasoning_can_include_local_ml_prediction_without_execution(self):
        class LocalModel:
            def predict(self, message):
                return {"intent": "analizar_problema", "executable": False}

        result = NexusReasoner(ml_pipeline=LocalModel()).analyze(
            ReasoningRequest("Revisa los bugs.", {"bug": [{"id": 1}]})
        )

        self.assertEqual(result.interpretation["ml_prediction"]["intent"], "analizar_problema")
        self.assertFalse(result.interpretation["ml_prediction"]["executable"])

    def test_simple_analysis_distinguishes_fact_and_inference(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa los bugs del proyecto.",
            {"bug": [{"id": 1, "status": "abierto", "priority": "critica"}]},
        ))
        kinds = {finding.finding_type for finding in result.findings}
        self.assertEqual(result.status, "analyzed")
        self.assertIn("fact", kinds)
        self.assertIn("inference", kinds)
        self.assertFalse(result.executable)

    def test_compound_analysis_uses_graph_relationships(self):
        graph = KnowledgeGraph.from_devcontrol(
            {"proyecto": [{"id": 1, "name": "DevControl"}], "bug": [{"id": 2, "project_id": 1, "status": "abierto"}]}
        )
        result = NexusReasoner().analyze(ReasoningRequest("Analiza los bugs."), graph)
        self.assertTrue(any("bug:2" in finding.statement for finding in result.findings))

    def test_incomplete_request_reports_missing_information(self):
        result = NexusReasoner().analyze(ReasoningRequest("Revisa este proyecto"))
        self.assertEqual(result.status, "insufficient_data")
        self.assertTrue(any(finding.finding_type == "missing" for finding in result.findings))

    def test_contradictory_context_is_exposed(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "¿Qué ocurre?", {"bug": [{"id": 1}]},
            {"contradictions": [{"key": "bug", "records": [{"status": "abierto"}, {"status": "cerrado"}]}]},
        ))
        self.assertEqual(result.status, "contradictory")
        self.assertTrue(any(finding.finding_type == "contradiction" for finding in result.findings))


if __name__ == "__main__":
    unittest.main()
