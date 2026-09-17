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

    def test_sql_exception_extracts_location_and_separates_symptom_from_cause(self):
        report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "sql-1",
            error="QueryException: SQLSTATE[42S02] table users does not exist",
            stack_trace=" at app/Repositories/UserRepository.php:42",
            code=("UserRepository queries users",),
            code_intelligence={
                "symbols": [{
                    "name": "UserRepository",
                    "kind": "class",
                    "file": "app/Repositories/UserRepository.php",
                }],
            },
        ))
        self.assertEqual(report.error_type, "database")
        self.assertIn("app/Repositories/UserRepository.php:42", report.probable_locations)
        self.assertTrue(report.symptoms)
        self.assertTrue(report.consequences)
        self.assertNotEqual(report.symptoms[0], report.probable_causes[0])

    def test_http_failure_is_not_reported_as_a_database_cause(self):
        report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "http-1",
            error="HTTP 502 Bad Gateway",
            logs=("upstream unavailable",),
        ))
        self.assertEqual(report.error_type, "http")
        self.assertIn("http", report.probable_causes[0])
        self.assertNotIn("database", report.probable_causes)
        self.assertTrue(report.hypotheses)

    def test_compilation_and_runtime_errors_are_supported(self):
        compile_report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "compile-1",
            error="TypeError: incompatible type",
            logs=("build failed in src/App.ts:18",),
        ))
        runtime_report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "runtime-1",
            error="NullPointerException",
            stack_trace="src/Worker.java:77",
        ))
        self.assertEqual(compile_report.error_type, "compilation")
        self.assertEqual(runtime_report.error_type, "null_reference")
        self.assertIn("src/Worker.java:77", runtime_report.probable_locations)

    def test_ambiguous_signals_keep_alternatives_and_missing_evidence(self):
        report = NexusDiagnosticAI().diagnose(DiagnosticInput(
            "ambiguous-1",
            error="Connection refused while processing request",
            logs=("configuration may be invalid", "permission denied by upstream"),
        ))
        self.assertTrue(
            "authentication" in report.probable_causes[0]
            or "configuration" in report.probable_causes[0]
        )
        self.assertTrue(report.alternatives)
        self.assertTrue(report.hypotheses)
        self.assertTrue(report.missing_information)


if __name__ == "__main__":
    unittest.main()
