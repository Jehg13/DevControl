import unittest

from nexus_code_model import ImpactComponent, ImpactGraph
from nexus_diagnostics import DiagnosticInput, NexusDiagnosticAI
from nexus_reasoning import SolutionProposalEngine


class SolutionProposalTest(unittest.TestCase):
    def test_validated_database_diagnosis_produces_alternative_read_only_proposals(self):
        diagnosis = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "db-proposal",
            error="QueryException: relation users does not exist",
            logs=("SQLSTATE migration failed",),
            code=("app/Repositories/UserRepository.php:42",),
            recent_changes=("added users migration",),
            configuration=("database configured",),
        ))
        impact = ImpactGraph(
            ("app/Repositories/UserRepository.php",),
            (ImpactComponent("app/Services/UserService.php", "file", "direct", ({"source": "import"},)),),
            (),
        )
        result = SolutionProposalEngine().propose(diagnosis, impact=impact)
        self.assertEqual(result.status, "proposed")
        self.assertGreaterEqual(len(result.proposals), 2)
        self.assertTrue(all(not item.executable for item in result.proposals))
        self.assertTrue(all(item.affected_components for item in result.proposals))
        self.assertNotEqual(result.proposals[0].solution, result.proposals[1].solution)
        self.assertTrue(result.proposals[0].risks)
        self.assertTrue(result.proposals[0].required_tests)

    def test_ambiguous_diagnosis_preserves_alternative_causes(self):
        diagnosis = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "ambiguous-proposal",
            error="Connection refused",
            logs=("configuration may be invalid", "permission denied"),
        ))
        result = SolutionProposalEngine().propose(diagnosis)
        self.assertEqual(result.status, "proposed")
        causes = {item.cause for item in result.proposals}
        self.assertGreaterEqual(len(causes), 2)
        self.assertTrue(all(item.status == "proposed" for item in result.proposals))

    def test_unvalidated_diagnosis_does_not_generate_solution(self):
        diagnosis = NexusDiagnosticAI().diagnose(DiagnosticInput("unknown"))
        result = SolutionProposalEngine().propose(diagnosis)
        self.assertEqual(result.status, "insufficient_evidence")
        self.assertEqual(result.proposals, ())
        self.assertFalse(result.executable)


if __name__ == "__main__":
    unittest.main()
