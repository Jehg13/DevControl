import unittest

from nexus_diagnostics import DiagnosticInput, NexusDiagnosticAI


class NexusDiagnosticsTest(unittest.TestCase):
    def test_combines_signals_and_produces_traceable_report(self):
        report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "db-1",
            error="SQLSTATE migration failed with 500 in production",
            logs=("QueryException: relation users does not exist",),
            recent_changes=("added users migration",),
            code=("UserRepository queries users table",),
            configuration=("database configured",),
            history=("previous deploy succeeded",),
        ))
        self.assertEqual(report.status, "supported")
        self.assertIn("database", report.probable_causes[0])
        self.assertEqual(report.severity, "critical")
        self.assertTrue(report.trace_id)
        self.assertGreaterEqual(report.confidence, 0.45)

    def test_abstains_without_evidence(self):
        report = NexusDiagnosticAI().diagnose(DiagnosticInput("unknown"))
        self.assertEqual(report.status, "insufficient_evidence")
        self.assertEqual(report.probable_causes, ())
        self.assertIn("No tengo evidencia suficiente", report.proposed_solution)
        self.assertGreater(report.missing_information.__len__(), 0)


if __name__ == "__main__":
    unittest.main()
