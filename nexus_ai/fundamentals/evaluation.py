"""Evaluation for conceptual understanding and evidence-based code recognition."""

from dataclasses import dataclass
from typing import Any

from .knowledge import FundamentalsKnowledgeBase


@dataclass(frozen=True)
class EvaluationResult:
    total: int
    correct: int
    score: float
    failures: tuple[dict[str, Any], ...]


class FundamentalsEvaluator:
    def __init__(self, knowledge: FundamentalsKnowledgeBase | None = None) -> None:
        self.knowledge = knowledge or FundamentalsKnowledgeBase()

    def evaluate(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = record["expected_concepts"]
            actual = {item.concept_id for item in self.knowledge.recognize(record["code"])}
            missing = sorted(set(expected) - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(total, total - len(failures), (total - len(failures)) / total if total else 0.0, tuple(failures))

    def evaluate_paradigms(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_paradigms"])
            actual = {
                item.paradigm_id
                for item in self.knowledge.recognize_paradigms(record["code"])
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_oop(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_findings"])
            actual = {
                item.principle
                for item in self.knowledge.analyze_oop(record["code"]).findings
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_patterns(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_patterns"])
            actual = {
                item.pattern_id
                for item in self.knowledge.recognize_patterns(record["code"])
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_architectures(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_architectures"])
            actual = {
                item.architecture_id
                for item in self.knowledge.recognize_architecture(record["files"])
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_protocols(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_protocols"])
            actual = {
                item.protocol_id
                for item in self.knowledge.recognize_protocols(record["files"])
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_databases(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.database_id
                for item in self.knowledge.analyze_database(record["files"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_git(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.git_id
                for item in self.knowledge.analyze_git(record["repository"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_testing(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.test_id
                for item in self.knowledge.analyze_tests(record["files"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_html(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.html_id
                for item in self.knowledge.analyze_html(record["html"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_css(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.css_id
                for item in self.knowledge.analyze_css(record["css"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_javascript(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.javascript_id
                for item in self.knowledge.analyze_javascript(record["javascript"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_browser(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.browser_id
                for item in self.knowledge.analyze_browser(record["browser"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_tailwind(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.tailwind_id
                for item in self.knowledge.analyze_tailwind(record["tailwind"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_bootstrap(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.bootstrap_id
                for item in self.knowledge.analyze_bootstrap(record["bootstrap"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_react(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.react_id
                for item in self.knowledge.analyze_react(record["react"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_vue(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.vue_id
                for item in self.knowledge.analyze_vue(record["vue"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_quasar(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.quasar_id
                for item in self.knowledge.analyze_quasar(record["quasar"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_frontend_architecture(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.architecture_id
                for item in self.knowledge.analyze_frontend_architecture(record["frontend"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_php(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {item.php_id for item in self.knowledge.analyze_php(record["php"]).observations}
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )

    def evaluate_advanced_php(self, records: list[dict[str, Any]]) -> EvaluationResult:
        failures = []
        for record in records:
            expected = set(record["expected_observations"])
            actual = {
                item.php_id
                for item in self.knowledge.analyze_advanced_php(record["php"]).observations
            }
            missing = sorted(expected - actual)
            if missing:
                failures.append({"id": record["id"], "missing": missing})
        total = len(records)
        return EvaluationResult(
            total,
            total - len(failures),
            (total - len(failures)) / total if total else 0.0,
            tuple(failures),
        )
