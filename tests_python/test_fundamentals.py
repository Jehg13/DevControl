import json
import unittest
from pathlib import Path

from nexus_ai.fundamentals import FundamentalsEvaluator, FundamentalsKnowledgeBase


class FundamentalsTests(unittest.TestCase):
    def setUp(self):
        self.knowledge = FundamentalsKnowledgeBase()

    def test_explanations_are_conceptual_and_separate_from_examples(self):
        explanation = self.knowledge.explain("time_complexity")
        self.assertEqual(explanation["category"], "complexity")
        self.assertIn("running time", explanation["definition"])
        self.assertNotIn("code", explanation)

    def test_recognizes_real_python_evidence_without_claiming_unknown_concepts(self):
        observations = self.knowledge.recognize(
            "values = [1, 2, 3]\nfor value in values:\n    print(value)"
        )
        concepts = {item.concept_id for item in observations}
        self.assertIn("data_structures", concepts)
        self.assertIn("algorithms", concepts)
        self.assertNotIn("parallelism", concepts)

    def test_evaluates_checked_in_fundamentals_examples(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)
        self.assertEqual(result.score, 1.0)

    def test_invalid_source_produces_no_false_recognition(self):
        self.assertEqual(self.knowledge.recognize("def broken(:"), [])

    def test_recognizes_algorithmic_structures_and_recursion(self):
        observations = self.knowledge.recognize(
            "from collections import deque\n"
            "from functools import lru_cache\n"
            "@lru_cache\n"
            "def solve(n):\n"
            "    if n < 2:\n"
            "        return n\n"
            "    return solve(n - 1) + solve(n - 2)\n"
            "queue = deque(items)\n"
            "queue.popleft()"
        )
        concepts = {item.concept_id for item in observations}
        self.assertTrue({"queues", "recursion", "dynamic_programming"} <= concepts)

    def test_proposes_deque_for_quadratic_fifo_list_removal(self):
        analysis = self.knowledge.analyze(
            "items = list(values)\n"
            "while items:\n"
            "    item = items.pop(0)\n"
            "    consume(item)\n"
        )
        self.assertTrue(analysis.recommendations)
        self.assertIn("deque", analysis.recommendations[0].alternative)

    def test_evaluates_algorithm_problem_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "algorithms_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)

    def test_explains_paradigm_tradeoffs(self):
        profile = self.knowledge.explain_paradigm("functional")
        self.assertIn("testability", profile["advantages"])
        self.assertTrue(profile["limitations"])
        self.assertTrue(profile["uses"])

    def test_recognizes_multiple_paradigms_with_evidence(self):
        observations = self.knowledge.recognize_paradigms(
            "from typing import Generic, TypeVar\n"
            "T = TypeVar('T')\n"
            "class Box(Generic[T]):\n"
            "    def __init__(self, value):\n"
            "        self._value = value\n"
            "    def get(self):\n"
            "        return self._value\n"
        )
        paradigms = {item.paradigm_id for item in observations}
        self.assertTrue({"generic", "object_oriented", "encapsulation"} <= paradigms)

    def test_evaluates_paradigms_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "paradigms_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_paradigms(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)
        self.assertEqual(result.failures, ())

    def test_explains_object_oriented_design_principles(self):
        profile = self.knowledge.explain("dependency_injection")
        self.assertIn("dependencies are explicit", profile["principles"])
        self.assertTrue(profile["definition"])

    def test_detects_dependency_inversion_and_contract_evidence(self):
        analysis = self.knowledge.analyze_oop(
            "class Service:\n"
            "    def __init__(self, client):\n"
            "        self.client = client\n"
            "    def run(self):\n"
            "        assert self.client\n"
            "        return self.client.get()\n"
        )
        principles = {item.principle for item in analysis.findings}
        self.assertIn("dependency_injection", principles)
        self.assertIn("design_by_contract", principles)

    def test_evaluates_oop_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "oop_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_oop(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)

    def test_explains_pattern_tradeoffs(self):
        profile = self.knowledge.pattern_profiles()["singleton"]
        self.assertIn("global state and hidden dependencies", profile["risk"])
        self.assertIn("explicit process-wide invariant", profile["uses"])

    def test_recognizes_patterns_and_keeps_recommendations_conditional(self):
        analysis = self.knowledge.analyze_patterns(
            "class CacheProxy:\n"
            "    def __init__(self, service):\n"
            "        self.service = service\n"
            "    def get(self, key):\n"
            "        return self.service.get(key)\n"
        )
        observed = {item.pattern_id for item in analysis.observations}
        self.assertTrue({"proxy", "dependency_injection"} <= observed)
        self.assertNotIn("singleton", {item.pattern_id for item in analysis.recommendations})

    def test_evaluates_design_patterns_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "patterns_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_patterns(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)

    def test_explains_software_architecture_tradeoffs(self):
        profile = self.knowledge.explain_architecture("hexagonal")
        self.assertIn("ports", profile["definition"])
        self.assertTrue(profile["tradeoffs"])

    def test_recognizes_architecture_from_project_boundaries(self):
        analysis = self.knowledge.analyze_architecture({
            "services/orders/main.py": "def publish(): pass",
            "services/payments/main.py": "def subscribe(): pass",
            "events/order_created.py": "class OrderCreated: pass",
            "docker-compose.yml": "services:\n  orders:\n  payments:",
        })
        architectures = {item.architecture_id for item in analysis.observations}
        self.assertTrue({"microservices", "event_driven", "distributed_systems"} <= architectures)
        self.assertEqual(analysis.primary_architecture, "microservices")

    def test_evaluates_software_architecture_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "architectures_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_architectures(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)
        self.assertEqual(result.failures, ())

    def test_explains_protocol_security_tradeoffs(self):
        profile = self.knowledge.explain_protocol("csrf")
        self.assertIn("cross-site", profile["definition"])
        self.assertTrue(profile["risks"])

    def test_recognizes_api_protocols_from_project_evidence(self):
        observations = self.knowledge.recognize_protocols({
            "api/routes.py": "@app.get('/orders')\nreturn JSONResponse([])",
            "api/auth.py": "response.headers['Set-Cookie'] = 'session=abc'",
        })
        protocols = {item.protocol_id for item in observations}
        self.assertTrue({"http", "rest", "json", "cookies"} <= protocols)

    def test_diagnoses_frontend_backend_communication_errors(self):
        diagnoses = self.knowledge.diagnose_communication({
            "status": 403,
            "cross_origin": True,
            "origin": "https://frontend.test",
            "response_headers": {"content-type": "application/json"},
            "request_headers": {},
            "csrf_required": True,
            "expected_json": True,
        })
        issues = {item.issue for item in diagnoses}
        self.assertIn("cors_origin_rejected", issues)
        self.assertIn("authentication_or_csrf_missing", issues)
        self.assertIn("csrf_token_missing", issues)

    def test_evaluates_api_protocol_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "protocols_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_protocols(records)
        self.assertEqual(result.total, 4)
        self.assertEqual(result.correct, 4)
        self.assertEqual(result.failures, ())

    def test_explains_database_tradeoffs(self):
        profile = self.knowledge.explain_database("indexes")
        self.assertIn("access paths", profile["definition"])
        self.assertTrue(profile["risks"])

    def test_detects_query_performance_and_integrity_problems(self):
        analysis = self.knowledge.analyze_database(
            "SELECT * FROM orders JOIN users WHERE LOWER(users.email) = 'a@example.test';"
        )
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"select_star", "unbounded_read", "join_without_on"} <= issues)
        self.assertIn("function_on_predicate_column", issues)

    def test_detects_orm_n_plus_one(self):
        analysis = self.knowledge.analyze_database({
            "orders.py": "for order in orders:\n    items = OrderItem.query.filter_by(order_id=order.id).all()",
        })
        self.assertIn("n_plus_one", {item.issue for item in analysis.findings})

    def test_evaluates_database_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "databases_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_databases(records)
        self.assertEqual(result.total, 3)
        self.assertEqual(result.correct, 3)
        self.assertEqual(result.failures, ())

    def test_explains_git_history_tradeoffs(self):
        profile = self.knowledge.explain_git("rebase")
        self.assertIn("rewrites", profile["definition"])
        self.assertTrue(profile["risks"])

    def test_analyzes_git_conflicts_and_work_item_traceability(self):
        analysis = self.knowledge.analyze_git({
            "commits": [{"message": "Fix BUG-42 order totals"}],
            "conflicts": [{"file": "app/Order.php"}],
            "bugs": [{"id": "BUG-42", "title": "order totals"}],
        })
        issues = {item.issue for item in analysis.findings}
        self.assertIn("unresolved_conflicts", issues)
        self.assertIn("change_related_to_work_item", issues)
        relation = next(item for item in analysis.findings if item.issue == "change_related_to_work_item")
        self.assertEqual(relation.related_records[0]["id"], "BUG-42")

    def test_evaluates_git_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "git_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_git(records)
        self.assertEqual(result.total, 3)
        self.assertEqual(result.correct, 3)
        self.assertEqual(result.failures, ())

    def test_explains_testing_tradeoffs(self):
        profile = self.knowledge.explain_testing("coverage")
        self.assertIn("executed code", profile["definition"])
        self.assertTrue(profile["risks"])

    def test_analyzes_test_behavior_and_doubles(self):
        analysis = self.knowledge.analyze_tests({
            "tests/test_orders.py": "from unittest.mock import patch\n"
            "def test_total_rejects_negative_quantity():\n"
            "    with patch('payments.charge') as charge:\n"
            "        assert total(-1) == 0\n"
            "        charge.assert_not_called()\n"
        })
        ids = {item.test_id for item in analysis.observations}
        self.assertTrue({"unit_tests", "assertions", "mocks"} <= ids)
        self.assertIn("total rejects negative quantity", analysis.observations[0].behavior)

    def test_evaluates_testing_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "testing_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_testing(records)
        self.assertEqual(result.total, 3)
        self.assertEqual(result.correct, 3)
        self.assertEqual(result.failures, ())

    def test_analyzes_html_structure_accessibility_and_seo(self):
        analysis = self.knowledge.analyze_html({"index.html": "<!doctype html><html lang='es'><head><title>Home</title>"
            "<meta name='description' content='Home page'></head><body><main><form><input name='q'>"
            "</form><img src='logo.png' alt='Logo'><table><tr><th>Item</th></tr></table>"
            "<a href='/home'>Home</a></main></body></html>"})
        ids = {item.html_id for item in analysis.observations}
        self.assertTrue({"document_structure", "semantic_html", "forms", "inputs", "tables", "links",
                         "media", "metadata", "seo_basics"} <= ids)
        self.assertNotIn("image_without_alt", {item.issue for item in analysis.findings})

    def test_detects_html_accessibility_errors(self):
        analysis = self.knowledge.analyze_html({"index.html": "<html><body><img src='x'><form><input></form>"
            "<table><tr><td>x</td></tr></table><a>Action</a></body></html>"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"image_without_alt", "input_without_name", "table_without_headers",
                         "missing_title", "missing_language", "link_without_href"} <= issues)

    def test_evaluates_html_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "html_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_html(records)
        self.assertEqual(result.total, 1)
        self.assertEqual(result.correct, 1)

    def test_analyzes_css_layout_responsive_and_modern_features(self):
        analysis = self.knowledge.analyze_css({"app.css": ":root { --space: 1rem; } "
            ".layout { display: grid; gap: var(--space); } @media (max-width: 700px) { "
            ".layout { grid-template-columns: 1fr; } } .card:hover { transition: transform .2s; } "
            ".card::before { content: ''; }"})
        ids = {item.css_id for item in analysis.observations}
        self.assertTrue({"selectors", "specificity", "grid", "variables", "media_queries",
                         "responsive_design", "transitions", "pseudo_elements"} <= ids)

    def test_detects_css_visual_risks(self):
        analysis = self.knowledge.analyze_css({"bad.css": "#app { width: 1200px; padding: 20px; "
            "overflow: hidden; position: absolute; } .button { color: red !important; }"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"important_overuse", "fixed_layout_without_responsive_rule",
                         "overflow_can_clip_positioned_content", "id_selector_specificity"} <= issues)

    def test_evaluates_css_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "css_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_css(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_javascript_modern_features(self):
        analysis = self.knowledge.analyze_javascript({"app.js": "const cache = new Map(); "
            "export async function load() { try { const response = await fetch('/api'); "
            "return await response.json(); } catch (error) { throw error; } } "
            "document.querySelector('#app').addEventListener('click', event => console.log(event));"})
        ids = {item.javascript_id for item in analysis.observations}
        self.assertTrue({"variables", "modules", "promises", "async_await", "fetch",
                         "error_handling", "events", "dom", "map", "functions", "closures"} <= ids)

    def test_detects_javascript_async_and_scope_risks(self):
        analysis = self.knowledge.analyze_javascript({"bad.js": "var value = 0; "
            "for (var i = 0; i < 3; i++) { setTimeout(() => console.log(i), 0); } "
            "fetch('/api/items');"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"function_scoped_var", "loop_closure_var_risk",
                         "fetch_without_error_handling"} <= issues)

    def test_evaluates_javascript_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "javascript_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_javascript(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_browser_runtime_features(self):
        analysis = self.knowledge.analyze_browser({"app.js": "document.addEventListener('DOMContentLoaded', () => { "
            "const cache = localStorage.getItem('items'); fetch('/api/items', { credentials: 'include' })"
            ".then(response => response.json()).catch(console.error); requestAnimationFrame(render); });"})
        ids = {item.browser_id for item in analysis.observations}
        self.assertTrue({"dom", "lifecycle", "local_storage", "fetch", "network_requests",
                         "cookies", "rendering", "event_loop"} <= ids)

    def test_detects_browser_security_and_lifecycle_risks(self):
        analysis = self.knowledge.analyze_browser({"app.js": "const token = localStorage.getItem('authorization'); "
            "document.querySelector('#app').innerHTML = token; setInterval(refresh, 1000); "
            "window.addEventListener('resize', onResize);"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"sensitive_data_in_web_storage", "unsafe_dom_rendering",
                         "interval_without_cleanup", "event_listener_without_cleanup"} <= issues)

    def test_evaluates_browser_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "browser_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_browser(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_tailwind_interface_features(self):
        analysis = self.knowledge.analyze_tailwind({"Card.jsx": "<div className='grid gap-4 p-4 md:grid-cols-2 "
            "dark:bg-slate-900'><button className='flex items-center bg-blue-600 px-4 text-white "
            "hover:bg-blue-700 focus:ring-2'>Save</button></div>"})
        ids = {item.tailwind_id for item in analysis.observations}
        self.assertTrue({"utility_classes", "responsive_variants", "states", "dark_mode", "spacing",
                         "typography", "flex", "grid", "colors"} <= ids)

    def test_detects_tailwind_conflicts(self):
        analysis = self.knowledge.analyze_tailwind({"bad.html": "<div class='w-full w-96 p-2 p-4 "
            "md:flex md:grid bg-red-500 bg-blue-500'></div>"})
        self.assertIn("conflicting_utilities", {item.issue for item in analysis.findings})

    def test_evaluates_tailwind_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "tailwind_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_tailwind(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_recognizes_bootstrap_structure(self):
        analysis = self.knowledge.analyze_bootstrap({"home.html": "<link rel='stylesheet' href='bootstrap.min.css'>"
            "<div class='container'><div class='row'><div class='col-md-6'><div class='card'>"
            "<button class='btn btn-primary'>Open</button></div></div></div></div>"})
        ids = {item.bootstrap_id for item in analysis.observations}
        self.assertTrue({"bootstrap_detection", "containers", "grid", "breakpoints",
                         "cards", "buttons"} <= ids)

    def test_detects_bootstrap_structure_risks(self):
        analysis = self.knowledge.analyze_bootstrap({"bad.html": "<div class='col-md-6 modal'></div>"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"columns_without_row", "modal_trigger_contract_missing"} <= issues)

    def test_evaluates_bootstrap_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "bootstrap_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_bootstrap(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_react_components_and_hooks(self):
        analysis = self.knowledge.analyze_react({"App.jsx": "import { useEffect, useState } from 'react'; "
            "export function App({ user }) { const [items, setItems] = useState([]); useEffect(() => { "
            "fetch('/api/items').then(r => r.json()).then(setItems); }, []); return <ul>{items.map(item => "
            "<li key={item.id}>{item.name}</li>)}</ul>; }"})
        ids = {item.react_id for item in analysis.observations}
        self.assertTrue({"components", "jsx", "props", "state", "hooks", "useState", "useEffect",
                         "rendering", "reconciliation"} <= ids)

    def test_detects_react_effect_and_reconciliation_risks(self):
        analysis = self.knowledge.analyze_react({"List.jsx": "function List({ value }) { useEffect(() => "
            "{ fetch('/api'); }, []); return value.map(item => <div>{item.name}</div>); }"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"effect_request_without_cleanup", "effect_dependency_risk",
                         "mapped_elements_without_key"} <= issues)

    def test_evaluates_react_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "react_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_react(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_vue_composition_api(self):
        analysis = self.knowledge.analyze_vue({"App.vue": "<script setup> import { ref, computed, onMounted } from 'vue'; "
            "const props = defineProps({ user: Object }); const emit = defineEmits(['save']); const items = ref([]); "
            "const total = computed(() => items.value.length); onMounted(() => {}); </script><template><form "
            "@submit.prevent=\"emit('save')\"><slot /><button>{{ total }}</button></form></template>"})
        ids = {item.vue_id for item in analysis.observations}
        self.assertTrue({"components", "props", "emits", "reactive_state", "computed", "lifecycle",
                         "slots", "composition_api", "vue_version_3"} <= ids)

    def test_detects_vue_lifecycle_and_version_risks(self):
        analysis = self.knowledge.analyze_vue({"Legacy.vue": "export default { mounted() { "
            "window.addEventListener('resize', this.onResize); } }; "
            "const app = createApp(App);"})
        issues = {item.issue for item in analysis.findings}
        self.assertIn("mixed_vue_api_versions", issues)
        self.assertIn("lifecycle_setup_without_teardown", issues)

    def test_evaluates_vue_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "vue_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_vue(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_quasar_project_structure(self):
        analysis = self.knowledge.analyze_quasar({"src/layouts/MainLayout.vue": "<template><q-layout><q-page-container>"
            "<router-view /></q-page-container></q-layout></template>", "src/pages/IndexPage.vue": "<template>"
            "<q-page><q-table :rows='rows' :columns='columns' row-key='id' /></q-page></template>",
            "quasar.config.js": "module.exports = { framework: { plugins: ['Notify'] }, boot: ['axios'] }"})
        ids = {item.quasar_id for item in analysis.observations}
        self.assertTrue({"quasar_cli", "components", "layouts", "pages", "boot_files", "plugins",
                         "qtable", "routing", "configuration"} <= ids)

    def test_detects_quasar_component_contract_risks(self):
        analysis = self.knowledge.analyze_quasar({"Form.vue": "<q-table /><q-form /><q-dialog />"})
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"qtable_contract_incomplete", "qform_without_validation_or_submit_contract",
                         "dialog_dismissal_not_visible"} <= issues)

    def test_evaluates_quasar_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "quasar_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_quasar(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_frontend_architecture_and_boundaries(self):
        analysis = self.knowledge.analyze_frontend_architecture({
            "src/components/Button.tsx": "export function Button(){ return <button />; }",
            "src/hooks/useAuth.ts": "export function useAuth(){ return useContext(AuthContext); }",
            "src/api/client.ts": "export const apiClient = axios.create({});",
            "src/router.tsx": "<Route path='/login' element={<Login />} />",
            "src/pages/Login.tsx": "const [loading,setLoading]=useState(false); const [error,setError]=useState(null); return <form />;",
        })
        ids = {item.architecture_id for item in analysis.observations}
        self.assertTrue({"component_architecture", "composables_hooks", "api_layers", "routing",
                         "state_management", "forms", "authentication", "error_handling",
                         "loading_states"} <= ids)

    def test_detects_frontend_architecture_contract_risks(self):
        analysis = self.knowledge.analyze_frontend_architecture({
            "src/pages/Login.tsx": "function Login(){ login(); return <form><input /></form>; }",
            "src/api/client.ts": "export async function load(){ return fetch('/items'); }",
        })
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"api_layer_without_error_or_loading_boundary",
                         "form_without_visible_validation",
                         "authentication_without_authorization_boundary"} <= issues)

    def test_evaluates_frontend_architecture_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "frontend_architecture_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_frontend_architecture(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_php_language_and_security_contracts(self):
        analysis = self.knowledge.analyze_php({
            "User.php": "<?php declare(strict_types=1); namespace App; interface Contract {} "
            "enum Role: string { case ADMIN = 'admin'; } trait Audit {} "
            "final class User implements Contract { use Audit; public function __construct(private int $id) {} }",
            "Controller.php": "<?php session_start(); $payload = unserialize($_POST['payload']); "
            "include $_GET['page']; $query = 'SELECT * FROM users WHERE id = ' . $_GET['id'];",
        })
        ids = {item.php_id for item in analysis.observations}
        self.assertTrue({"syntax", "types", "namespaces", "interfaces", "enums", "traits",
                         "classes", "functions", "sessions", "security"} <= ids)
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"unsafe_unserialize", "dynamic_file_path", "sql_interpolation"} <= issues)

    def test_evaluates_php_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "php_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_php(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)

    def test_analyzes_advanced_php_runtime_and_boundaries(self):
        analysis = self.knowledge.analyze_advanced_php({
            "composer.json": '{"autoload":{"psr-4":{"App\\\\":"src/"}}}',
            "Worker.php": "<?php require 'vendor/autoload.php'; "
            "final class Worker { public function run(){ return iterator_to_array(new ArrayIterator([])); } } "
            "#[Route('/jobs')] $r = new ReflectionClass(Worker::class);",
            "bin/job.php": "<?php $input = stream_get_contents(STDIN); "
            "$p = proc_open($_GET['command'], [], $pipes); echo getenv('SECRET'); "
            "unserialize($_POST['payload']); @file_get_contents($url); ini_set('opcache.enable', 0);",
        })
        ids = {item.php_id for item in analysis.observations}
        self.assertTrue({"autoloading", "composer", "psr", "spl", "reflection", "attributes",
                         "performance", "streams", "processes", "cli", "environment",
                         "serialization", "error_handling", "opcache"} <= ids)
        issues = {item.issue for item in analysis.findings}
        self.assertTrue({"unsafe_process_execution", "serialization_boundary_risk",
                         "global_error_suppression", "opcache_disabled"} <= issues)

    def test_evaluates_advanced_php_dataset(self):
        path = Path(__file__).parents[1] / "nexus_ai" / "fundamentals" / "advanced_php_dataset.jsonl"
        records = [json.loads(line) for line in path.read_text(encoding="utf-8").splitlines() if line.strip()]
        result = FundamentalsEvaluator(self.knowledge).evaluate_advanced_php(records)
        self.assertEqual(result.total, 2)
        self.assertEqual(result.correct, 2)


if __name__ == "__main__":
    unittest.main()
