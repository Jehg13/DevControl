import unittest

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.reasoning import NexusReasoner, ReasoningRequest


class TechnicalReasoningTests(unittest.TestCase):
    def test_reasoning_uses_fundamentals_for_supplied_code(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza la complejidad.",
            context={"code": "values = [1, 2]\nfor value in values:\n    print(value)"},
        ))

        observations = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "computer_science_fundamentals"
        ]
        self.assertTrue(observations)
        self.assertIn(
            "algorithms",
            {item.evidence[0]["concept_id"] for item in observations},
        )

    def test_reasoning_uses_programming_paradigms_for_supplied_code(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza el diseño.",
            context={"code": "class Service:\n    def run(self):\n        return 1"},
        ))
        paradigms = {
            finding.evidence[0]["paradigm_id"]
            for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "programming_paradigms"
        }
        self.assertIn("object_oriented", paradigms)

    def test_reasoning_preserves_oop_findings_as_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa el diseño OO.",
            context={"code": "class Service:\n    def __init__(self):\n        self.client = HttpClient()"},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "advanced_object_oriented_programming"
        ]
        self.assertTrue(findings)
        self.assertIn("solid_dependency_inversion", findings[0].evidence[0]["principle"])

    def test_reasoning_preserves_design_pattern_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa el patrón.",
            context={"code": "class ReportFactory:\n    def create(self):\n        return PdfReport()"},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "design_patterns"
        ]
        self.assertTrue(findings)
        self.assertIn("factory", {
            item.evidence[0].get("pattern_id")
            for item in findings
        })

    def test_reasoning_preserves_software_architecture_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza la arquitectura del proyecto.",
            context={"project_files": {
                "app/controllers/home.py": "class HomeController: pass",
                "app/services/home.py": "class HomeService: pass",
                "app/repositories/home.py": "class HomeRepository: pass",
                "app/models/home.py": "class Home: pass",
                "app/views/home.html": "<html></html>",
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "software_architecture"
        ]
        self.assertTrue(findings)
        self.assertIn("layered", {
            item.evidence[0].get("architecture_id")
            for item in findings
        })

    def test_reasoning_preserves_api_protocol_diagnoses(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica el error del frontend.",
            context={"communication_trace": {
                "status": 429,
                "request_headers": {},
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "api_protocols"
        ]
        self.assertTrue(findings)
        self.assertIn("rate_limit_exceeded", {
            item.evidence[0].get("issue")
            for item in findings
        })

    def test_reasoning_preserves_database_findings(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza el rendimiento de esta query.",
            context={"code": "SELECT * FROM orders WHERE status = 'open';"},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "database_intelligence"
        ]
        self.assertTrue(findings)
        self.assertIn("select_star", {
            item.evidence[0].get("issue")
            for item in findings
        })

    def test_reasoning_preserves_git_traceability(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Relaciona los cambios con el bug.",
            context={"git_repository": {
                "commits": [{"message": "Fix BUG-42 order totals"}],
                "bugs": [{"id": "BUG-42", "title": "order totals"}],
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "git_intelligence"
        ]
        self.assertTrue(findings)
        self.assertIn("change_related_to_work_item", {
            item.evidence[0].get("issue")
            for item in findings
        })

    def test_reasoning_preserves_testing_behavior(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza qué valida esta prueba.",
            context={"test_files": {
                "tests/test_orders.py": "def test_total_rejects_negative_quantity():\n"
                "    assert total(-1) == 0\n",
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "testing_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            "total rejects negative quantity" in finding.evidence[0].get("behavior", "")
            for finding in findings
        ))

    def test_reasoning_preserves_html_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa la accesibilidad de esta página.",
            context={"html_files": {"index.html": "<html><body><img src='logo.png'></body></html>"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "html_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "image_without_alt"
            for finding in findings
        ))

    def test_reasoning_preserves_css_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica este CSS.",
            context={"css_files": {"app.css": "#app { width: 1200px; }"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "css_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "fixed_layout_without_responsive_rule"
            for finding in findings
        ))

    def test_reasoning_preserves_javascript_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica este JavaScript.",
            context={"javascript_files": {"app.js": "fetch('/api/items');"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "javascript_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "fetch_without_error_handling"
            for finding in findings
        ))

    def test_reasoning_preserves_browser_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica este problema del navegador.",
            context={"browser_files": {"app.js": "fetch('/api/items');"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "browser_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "network_failure_not_handled"
            for finding in findings
        ))

    def test_reasoning_preserves_tailwind_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa estas clases Tailwind.",
            context={"tailwind_files": {"index.html": "<div class='w-full w-96'></div>"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "tailwind_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "conflicting_utilities"
            for finding in findings
        ))

    def test_reasoning_preserves_bootstrap_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Identifica el framework CSS.",
            context={"bootstrap_files": {"index.html": "<div class='col-md-6'></div>"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "bootstrap_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "columns_without_row"
            for finding in findings
        ))

    def test_reasoning_preserves_react_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica esta aplicación React.",
            context={"react_files": {"List.jsx": "function List({ value }) { return value.map(item => <div>{item.name}</div>); }"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "react_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "mapped_elements_without_key"
            for finding in findings
        ))

    def test_reasoning_preserves_vue_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica este componente Vue.",
            context={"vue_files": {"App.vue": "<script setup> import { ref } from 'vue'; const value = ref(0); </script><template><button>{{ value }}</button></template>"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "vue_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("vue_id") == "composition_api"
            for finding in findings
        ))

    def test_reasoning_preserves_quasar_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Revisa este proyecto Quasar.",
            context={"quasar_files": {"Page.vue": "<q-table />"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "quasar_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "qtable_contract_incomplete"
            for finding in findings
        ))

    def test_reasoning_preserves_frontend_architecture_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Explica la arquitectura frontend.",
            context={"frontend_architecture_files": {
                "src/api/client.ts": "export const apiClient = axios.create({});",
                "src/pages/Home.tsx": "const [loading,setLoading]=useState(false);",
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "frontend_architecture_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("architecture_id") == "api_layers"
            for finding in findings
        ))

    def test_reasoning_preserves_php_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Analiza este código PHP.",
            context={"php_files": {"index.php": "<?php unserialize($_POST['payload']);"}},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "php_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "unsafe_unserialize"
            for finding in findings
        ))

    def test_reasoning_preserves_advanced_php_evidence(self):
        result = NexusReasoner().analyze(ReasoningRequest(
            "Diagnostica el runtime avanzado de PHP.",
            context={"advanced_php_files": {
                "bin/job.php": "<?php $p = proc_open($_GET['command'], [], $pipes);"
            }},
        ))
        findings = [
            finding for finding in result.findings
            if finding.evidence
            and finding.evidence[0].get("source") == "advanced_php_intelligence"
        ]
        self.assertTrue(findings)
        self.assertTrue(any(
            finding.evidence[0].get("issue") == "unsafe_process_execution"
            for finding in findings
        ))

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
