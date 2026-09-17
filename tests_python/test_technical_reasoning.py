import unittest

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.reasoning import NexusReasoner, ReasoningRequest


class TechnicalReasoningTests(unittest.TestCase):
    def test_conclusion_is_based_on_reproducible_evidence(self):
        request = ReasoningRequest(
            "Revisa los bugs.",
            {"bug": [{"id": 1, "status": "abierto", "priority": "alta"}]},
        )
        first = NexusReasoner().analyze(request)
        second = NexusReasoner().analyze(request)

        self.assertEqual(first.conclusion.finding_type, "conclusion")
        self.assertTrue(first.conclusion.evidence)
        self.assertEqual(first.evidence_fingerprint, second.evidence_fingerprint)
        self.assertNotIn(first.conclusion.statement, [
            finding.statement for finding in first.findings if finding.finding_type == "fact"
        ])

    def test_insufficient_evidence_produces_missing_conclusion(self):
        result = NexusReasoner().analyze(ReasoningRequest("Revisa este proyecto"))

        self.assertEqual(result.status, "insufficient_data")
        self.assertEqual(result.conclusion.confidence, 0.0)
        self.assertTrue(any(f.finding_type == "missing" for f in result.findings))

    def test_multiple_hypotheses_are_preserved(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza los problemas.",
            {"bug": [
                {"id": 1, "status": "abierto", "priority": "alta"},
                {"id": 2, "status": "pendiente", "priority": "baja"},
            ]},
        ))

        hypotheses = [f for f in result.findings if f.finding_type == "hypothesis"]
        self.assertGreaterEqual(len(hypotheses), 2)
        self.assertTrue(all(f.status == "active" for f in hypotheses))
        self.assertEqual(result.conclusion.confidence, 0.8)

    def test_contradicted_hypotheses_are_discarded(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "¿Qué ocurre?",
            {"bug": [{"id": 1, "status": "abierto", "priority": "alta"}]},
            {"contradictions": [{
                "key": "bug",
                "records": [{"status": "abierto"}, {"status": "cerrado"}],
            }]},
        ))

        self.assertEqual(result.status, "contradictory")
        self.assertTrue(any(
            finding.finding_type == "hypothesis" and finding.status == "discarded"
            for finding in result.findings
        ))
        self.assertEqual(result.conclusion.confidence, 0.0)

    def test_knowledge_and_data_remain_evidence_layers(self):
        graph = KnowledgeGraph.from_devcontrol({
            "proyecto": [{"id": 1, "name": "DevControl"}],
            "bug": [{"id": 2, "project_id": 1, "status": "abierto"}],
        })
        result = NexusReasoner().analyze(
            ReasoningRequest("Analiza los bugs.", {"bug": [{"id": 2}]}),
            graph,
        )

        self.assertTrue(any("bug:2" in finding.statement for finding in result.findings))
        self.assertTrue(result.conclusion.evidence)
        self.assertFalse(result.executable)


if __name__ == "__main__":
    unittest.main()
