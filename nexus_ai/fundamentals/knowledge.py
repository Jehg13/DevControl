"""Versioned, conceptual computer-science knowledge separate from code examples."""

from dataclasses import dataclass, field
import ast
import json
from html.parser import HTMLParser
from pathlib import Path
import re
from typing import Any, Mapping


@dataclass(frozen=True)
class FundamentalConcept:
    concept_id: str
    category: str
    definition: str
    principles: tuple[str, ...]
    related: tuple[str, ...] = ()

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.concept_id,
            "category": self.category,
            "definition": self.definition,
            "principles": list(self.principles),
            "related": list(self.related),
        }


@dataclass(frozen=True)
class CodeObservation:
    concept_id: str
    evidence: tuple[str, ...]
    complexity: dict[str, str] = field(default_factory=dict)
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "concept_id": self.concept_id,
            "evidence": list(self.evidence),
            "complexity": self.complexity,
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class CodeRecommendation:
    issue: str
    alternative: str
    justification: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "alternative": self.alternative,
            "justification": self.justification,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class AlgorithmAnalysis:
    observations: tuple[CodeObservation, ...]
    recommendations: tuple[CodeRecommendation, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "recommendations": [item.to_dict() for item in self.recommendations],
        }


@dataclass(frozen=True)
class ParadigmProfile:
    paradigm_id: str
    definition: str
    advantages: tuple[str, ...]
    limitations: tuple[str, ...]
    uses: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "id": self.paradigm_id,
            "definition": self.definition,
            "advantages": list(self.advantages),
            "limitations": list(self.limitations),
            "uses": list(self.uses),
        }


@dataclass(frozen=True)
class ParadigmObservation:
    paradigm_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "paradigm_id": self.paradigm_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class OopFinding:
    principle: str
    status: str
    issue: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "principle": self.principle,
            "status": self.status,
            "issue": self.issue,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class OopAnalysis:
    observations: tuple[ParadigmObservation, ...]
    findings: tuple[OopFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class DesignPatternObservation:
    pattern_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "pattern_id": self.pattern_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class PatternRecommendation:
    pattern_id: str
    status: str
    reason: str
    tradeoff: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "pattern_id": self.pattern_id,
            "status": self.status,
            "reason": self.reason,
            "tradeoff": self.tradeoff,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class DesignPatternAnalysis:
    observations: tuple[DesignPatternObservation, ...]
    recommendations: tuple[PatternRecommendation, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "recommendations": [item.to_dict() for item in self.recommendations],
        }


@dataclass(frozen=True)
class ArchitectureObservation:
    architecture_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "architecture_id": self.architecture_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class ArchitectureAnalysis:
    observations: tuple[ArchitectureObservation, ...]
    primary_architecture: str | None

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "primary_architecture": self.primary_architecture,
        }


@dataclass(frozen=True)
class ProtocolObservation:
    protocol_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "protocol_id": self.protocol_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class CommunicationDiagnosis:
    issue: str
    severity: str
    cause: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "severity": self.severity,
            "cause": self.cause,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class ApiProtocolAnalysis:
    observations: tuple[ProtocolObservation, ...]
    diagnoses: tuple[CommunicationDiagnosis, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "diagnoses": [item.to_dict() for item in self.diagnoses],
        }


@dataclass(frozen=True)
class DatabaseObservation:
    database_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "database_id": self.database_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class DatabaseFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class DatabaseAnalysis:
    observations: tuple[DatabaseObservation, ...]
    findings: tuple[DatabaseFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class GitObservation:
    git_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "git_id": self.git_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class GitFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]
    related_records: tuple[dict[str, Any], ...] = ()

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
            "related_records": [dict(item) for item in self.related_records],
        }


@dataclass(frozen=True)
class GitAnalysis:
    observations: tuple[GitObservation, ...]
    findings: tuple[GitFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class TestObservation:
    test_id: str
    evidence: tuple[str, ...]
    behavior: str
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "test_id": self.test_id,
            "evidence": list(self.evidence),
            "behavior": self.behavior,
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class TestFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class TestingAnalysis:
    observations: tuple[TestObservation, ...]
    findings: tuple[TestFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class HtmlObservation:
    html_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"html_id": self.html_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class HtmlFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class HtmlAnalysis:
    observations: tuple[HtmlObservation, ...]
    findings: tuple[HtmlFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class CssObservation:
    css_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"css_id": self.css_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class CssFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class CssAnalysis:
    observations: tuple[CssObservation, ...]
    findings: tuple[CssFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class JavaScriptObservation:
    javascript_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "javascript_id": self.javascript_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class JavaScriptFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class JavaScriptAnalysis:
    observations: tuple[JavaScriptObservation, ...]
    findings: tuple[JavaScriptFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class PHPObservation:
    php_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"php_id": self.php_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class PHPFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class PHPAnalysis:
    observations: tuple[PHPObservation, ...]
    findings: tuple[PHPFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class AdvancedPHPObservation:
    php_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"php_id": self.php_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class AdvancedPHPFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class AdvancedPHPAnalysis:
    observations: tuple[AdvancedPHPObservation, ...]
    findings: tuple[AdvancedPHPFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class BrowserObservation:
    browser_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"browser_id": self.browser_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class BrowserFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class BrowserAnalysis:
    observations: tuple[BrowserObservation, ...]
    findings: tuple[BrowserFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class TailwindObservation:
    tailwind_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"tailwind_id": self.tailwind_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class TailwindFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class TailwindAnalysis:
    observations: tuple[TailwindObservation, ...]
    findings: tuple[TailwindFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class BootstrapObservation:
    bootstrap_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"bootstrap_id": self.bootstrap_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class BootstrapFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class BootstrapAnalysis:
    observations: tuple[BootstrapObservation, ...]
    findings: tuple[BootstrapFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class ReactObservation:
    react_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"react_id": self.react_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class ReactFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class ReactAnalysis:
    observations: tuple[ReactObservation, ...]
    findings: tuple[ReactFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class VueObservation:
    vue_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"vue_id": self.vue_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class VueFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class VueAnalysis:
    observations: tuple[VueObservation, ...]
    findings: tuple[VueFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class QuasarObservation:
    quasar_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {"quasar_id": self.quasar_id, "evidence": list(self.evidence), "confidence": self.confidence}


@dataclass(frozen=True)
class QuasarFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class QuasarAnalysis:
    observations: tuple[QuasarObservation, ...]
    findings: tuple[QuasarFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


@dataclass(frozen=True)
class FrontendArchitectureObservation:
    architecture_id: str
    evidence: tuple[str, ...]
    confidence: float = 1.0

    def to_dict(self) -> dict[str, Any]:
        return {
            "architecture_id": self.architecture_id,
            "evidence": list(self.evidence),
            "confidence": self.confidence,
        }


@dataclass(frozen=True)
class FrontendArchitectureFinding:
    issue: str
    category: str
    severity: str
    explanation: str
    correction: str
    evidence: tuple[str, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "issue": self.issue,
            "category": self.category,
            "severity": self.severity,
            "explanation": self.explanation,
            "correction": self.correction,
            "evidence": list(self.evidence),
        }


@dataclass(frozen=True)
class FrontendArchitectureAnalysis:
    observations: tuple[FrontendArchitectureObservation, ...]
    findings: tuple[FrontendArchitectureFinding, ...]

    def to_dict(self) -> dict[str, Any]:
        return {
            "observations": [item.to_dict() for item in self.observations],
            "findings": [item.to_dict() for item in self.findings],
        }


class _HtmlStructureParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.tags: list[tuple[str, int]] = []
        self.attributes: dict[tuple[str, int], dict[str, str]] = {}
        self.meta: list[tuple[str, str]] = []
        self.html_attributes: dict[str, str] = {}

    def handle_starttag(self, tag: str, attrs: list[tuple[str, str | None]]) -> None:
        name = tag.casefold()
        line = self.getpos()[0]
        values = {key.casefold(): value or "" for key, value in attrs}
        self.tags.append((name, line))
        self.attributes[(name, line)] = values
        if name == "html":
            self.html_attributes = values
        if name == "meta" and "name" in values:
            self.meta.append((values["name"], values.get("content", "")))


class FundamentalsKnowledgeBase:
    """Answers from concepts and recognizes conservative code patterns.

    Concepts are loaded independently from examples. Recognition is evidence
    based and returns no claim when the code does not support one.
    """

    def __init__(self, concepts: list[FundamentalConcept] | None = None) -> None:
        self._concepts = {item.concept_id: item for item in concepts or self.default_concepts()}

    @classmethod
    def from_jsonl(cls, path: str | Path) -> "FundamentalsKnowledgeBase":
        concepts: list[FundamentalConcept] = []
        with Path(path).open(encoding="utf-8") as handle:
            for line in handle:
                if not line.strip():
                    continue
                record = json.loads(line)
                concepts.append(FundamentalConcept(
                    record["id"], record["category"], record["definition"],
                    tuple(record["principles"]), tuple(record.get("related", [])),
                ))
        return cls(concepts)

    def get(self, concept_id: str) -> FundamentalConcept | None:
        return self._concepts.get(concept_id)

    def explain(self, concept_id: str) -> dict[str, Any]:
        concept = self.get(concept_id)
        if concept is None:
            raise KeyError(f"unknown fundamental concept: {concept_id}")
        return concept.to_dict()

    def explain_paradigm(self, paradigm_id: str) -> dict[str, Any]:
        profile = self.paradigm_profiles().get(paradigm_id)
        if profile is None:
            raise KeyError(f"unknown programming paradigm: {paradigm_id}")
        return profile.to_dict()

    def explain_architecture(self, architecture_id: str) -> dict[str, Any]:
        profile = self.architecture_profiles().get(architecture_id)
        if profile is None:
            raise KeyError(f"unknown software architecture: {architecture_id}")
        return {"id": architecture_id, **profile}

    def search(self, query: str) -> list[FundamentalConcept]:
        tokens = set(re.findall(r"[a-z0-9_]+", query.casefold()))
        return [
            concept for concept in self._concepts.values()
            if tokens & set(re.findall(r"[a-z0-9_]+", (
                f"{concept.concept_id} {concept.category} {concept.definition} "
                f"{' '.join(concept.principles)}"
            ).casefold()))
        ]

    def recognize(self, source: str, language: str = "python") -> list[CodeObservation]:
        if language.casefold() != "python":
            return []
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return []
        observations: list[CodeObservation] = []
        loops = [node for node in ast.walk(tree) if isinstance(node, (ast.For, ast.While))]
        nested_loop = any(
            isinstance(child, (ast.For, ast.While))
            for loop in loops for child in ast.walk(loop) if child is not loop
        )
        if any(isinstance(node, (ast.List, ast.ListComp)) for node in ast.walk(tree)):
            observations.append(CodeObservation(
                "data_structures", ("list literal or comprehension",), {"space": "O(n)"}, 0.9
            ))
        if any(isinstance(node, ast.Dict) for node in ast.walk(tree)):
            observations.append(CodeObservation(
                "hash_tables", ("dictionary literal",), {"average_lookup": "O(1)"}, 0.9
            ))
            observations.append(CodeObservation(
                "data_structures", ("dictionary literal",), {"average_lookup": "O(1)"}, 0.9
            ))
        if any(isinstance(node, ast.Set) for node in ast.walk(tree)):
            observations.append(CodeObservation(
                "sets", ("set literal",), {"average_membership": "O(1)"}, 0.9
            ))
            observations.append(CodeObservation(
                "hashing", ("set membership relies on hashing",), {"average_membership": "O(1)"}, 0.85
            ))
        calls = [
            node for node in ast.walk(tree)
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute)
        ]
        call_names = {node.func.attr for node in calls}
        named_calls = {
            node.func.id for node in ast.walk(tree)
            if isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
        }
        if "set" in named_calls:
            observations.append(CodeObservation(
                "sets", ("set constructor",), {"average_membership": "O(1)"}, 0.9
            ))
            observations.append(CodeObservation(
                "hash_tables", ("set constructor uses hashing",), {"average_membership": "O(1)"}, 0.8
            ))
        imports = {
            alias.name for node in ast.walk(tree)
            if isinstance(node, ast.Import)
            for alias in node.names
        }
        imports.update(
            alias.name for node in ast.walk(tree)
            if isinstance(node, ast.ImportFrom)
            for alias in node.names
        )
        if "array" in imports or any(
            isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == "array"
            for node in ast.walk(tree)
        ):
            observations.append(CodeObservation("arrays", ("array module construction",), {"index": "O(1)"}, 0.9))
        if "deque" in imports or {"popleft", "appendleft"} & call_names:
            observations.append(CodeObservation("queues", ("deque or end-removal operation",), {"enqueue": "O(1)", "dequeue": "O(1)"}, 0.85))
            if loops:
                observations.append(CodeObservation("traversals", ("queue consumed inside a loop",), {"time": "O(V + E) for adjacency lists"}, 0.7))
        if "heappush" in imports or "heappop" in imports or {"heapify", "heappush", "heappop"} & call_names:
            observations.append(CodeObservation("heaps", ("heap operation",), {"push": "O(log n)", "pop": "O(log n)"}, 0.9))
        if {"append", "pop"} <= call_names:
            observations.append(CodeObservation("stacks", ("append and pop operations",), {"push": "O(1)", "pop": "O(1)"}, 0.8))
        class_names = {
            node.name for node in ast.walk(tree) if isinstance(node, ast.ClassDef)
        }
        attributes = {
            node.attr for node in ast.walk(tree) if isinstance(node, ast.Attribute)
        }
        if "next" in attributes and class_names:
            observations.append(CodeObservation("linked_lists", ("class with next pointer",), {"lookup": "O(n)"}, 0.75))
        if class_names and ({"left", "right"} & attributes):
            observations.append(CodeObservation("trees", ("node with left/right child pointers",), {"search": "O(h)"}, 0.75))
        if any(isinstance(node, ast.Dict) for node in ast.walk(tree)) and any(
            "neighbor" in name.casefold() or "graph" in name.casefold()
            for name in attributes | class_names
        ):
            observations.append(CodeObservation("graphs", ("adjacency mapping or graph-named structure",), {"traversal": "O(V + E)"}, 0.7))
        function_names = {node.name for node in ast.walk(tree) if isinstance(node, ast.FunctionDef)}
        recursive = any(
            any(
                isinstance(child, ast.Call)
                and isinstance(child.func, ast.Name)
                and child.func.id == function.name
                for child in ast.walk(function)
            )
            for function in ast.walk(tree)
            if isinstance(function, ast.FunctionDef)
        )
        if recursive:
            observations.append(CodeObservation("recursion", ("function calls a named function",), {"space": "O(depth)"}, 0.7))
            observations.append(CodeObservation("traversals", ("recursive node exploration",), {"time": "depends on visited structure"}, 0.55))
        if {"sort", "sorted"} & call_names or any(
            isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == "sorted"
            for node in ast.walk(tree)
        ):
            observations.append(CodeObservation("sorting", ("sort or sorted call",), {"time": "O(n log n)"}, 0.9))
        if {"bisect", "index"} & call_names or any(
            isinstance(node, ast.Compare) and node.ops and isinstance(node.ops[0], ast.In)
            for node in ast.walk(tree)
        ):
            observations.append(CodeObservation("searching", ("membership or search operation",), {}, 0.65))
        if any(isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id == "hash"
               for node in ast.walk(tree)):
            observations.append(CodeObservation("hashing", ("hash call",), {"average_lookup": "O(1) with a hash table"}, 0.9))
        if any(isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute)
               and node.func.attr in {"get", "setdefault"} for node in ast.walk(tree)):
            observations.append(CodeObservation("hashing", ("hash-table access method",), {"average_lookup": "O(1)"}, 0.8))
        decorators = {
            decorator.id for node in ast.walk(tree) if isinstance(node, ast.FunctionDef)
            for decorator in node.decorator_list if isinstance(decorator, ast.Name)
        }
        if "lru_cache" in decorators or "cache" in decorators:
            observations.append(CodeObservation("dynamic_programming", ("memoization decorator",), {"time": "problem-dependent", "space": "O(states)"}, 0.95))
        if recursive and {"append", "pop"} <= call_names:
            observations.append(CodeObservation("backtracking", ("recursive search with mutable candidate state",), {}, 0.55))
        if any(isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id in {"min", "max"}
               for node in ast.walk(tree)):
            observations.append(CodeObservation("greedy_algorithms", ("local min/max selection",), {}, 0.55))
        if loops:
            observations.append(CodeObservation(
                "algorithms", ("loop traversal",),
                {"time": "O(n^k)" if nested_loop else "O(n)"}, 0.75
            ))
        if any(isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute)
               and node.func.attr in {"read", "write", "open"} for node in ast.walk(tree)):
            observations.append(CodeObservation("input_output", ("file read/write call",), {}, 0.85))
        if any(isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
               and node.func.id in {"Thread", "Process"} for node in ast.walk(tree)):
            observations.append(CodeObservation("concurrency", ("Thread or Process construction",), {}, 0.8))
        if any(isinstance(node, ast.FunctionDef) for node in ast.walk(tree)):
            observations.append(CodeObservation("runtime", ("function definition",), {}, 0.6))
        return observations

    def analyze(self, source: str, language: str = "python") -> AlgorithmAnalysis:
        observations = tuple(self.recognize(source, language))
        recommendations: list[CodeRecommendation] = []
        tree = None
        if language.casefold() == "python":
            try:
                tree = ast.parse(source)
            except SyntaxError:
                return AlgorithmAnalysis(observations, ())
        if tree is not None:
            nested_loops = [
                node for node in ast.walk(tree)
                if isinstance(node, (ast.For, ast.While))
                and any(isinstance(child, (ast.For, ast.While)) for child in ast.walk(node) if child is not node)
            ]
            if nested_loops and any(
                isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr == "append"
                for node in ast.walk(tree)
            ):
                recommendations.append(CodeRecommendation(
                    "nested traversal appends results and may be quadratic",
                    "Use a hash table or set for membership/indexing when the relation permits it",
                    "Indexed average O(1) lookup can reduce repeated O(n) scans to approximately O(n).",
                    ("nested loops", "append inside traversal"),
                ))
            if any(
                isinstance(node, ast.Call) and isinstance(node.func, ast.Attribute) and node.func.attr == "pop"
                and node.args and isinstance(node.args[0], ast.Constant) and node.args[0].value == 0
                for node in ast.walk(tree)
            ):
                recommendations.append(CodeRecommendation(
                    "removing index zero from a list shifts remaining elements",
                    "Use collections.deque for FIFO workloads",
                    "deque.popleft() is O(1), while list.pop(0) is O(n).",
                    ("list.pop(0)",),
                ))
        return AlgorithmAnalysis(observations, tuple(recommendations))

    def recognize_paradigms(self, source: str, language: str = "python") -> list[ParadigmObservation]:
        if language.casefold() != "python":
            return []
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return []
        nodes = list(ast.walk(tree))
        observations: list[ParadigmObservation] = []
        classes = [node for node in nodes if isinstance(node, ast.ClassDef)]
        functions = [node for node in nodes if isinstance(node, ast.FunctionDef)]
        calls = [node for node in nodes if isinstance(node, ast.Call)]
        call_names = {
            node.func.attr if isinstance(node.func, ast.Attribute) else node.func.id
            for node in calls
            if isinstance(node.func, (ast.Attribute, ast.Name))
        }
        imports = {
            alias.name.casefold() for node in nodes
            if isinstance(node, (ast.Import, ast.ImportFrom))
            for alias in node.names
        }
        if any(isinstance(node, (ast.Assign, ast.AugAssign, ast.For, ast.While)) for node in nodes):
            observations.append(ParadigmObservation(
                "procedural", ("assignments or control-flow statements",), 0.7
            ))
        if classes:
            observations.append(ParadigmObservation(
                "object_oriented", ("class declarations",), 0.95
            ))
        if any(isinstance(node, ast.Lambda) for node in nodes) or {"map", "filter", "reduce"} & call_names:
            observations.append(ParadigmObservation(
                "functional", ("lambda, higher-order function, or map/filter/reduce",), 0.85
            ))
        if any(isinstance(node, (ast.ListComp, ast.SetComp, ast.DictComp, ast.GeneratorExp)) for node in nodes):
            observations.append(ParadigmObservation(
                "declarative", ("comprehension or generator expression describes a result",), 0.7
            ))
        event_tokens = {"on_click", "on_event", "callback", "emit", "subscribe", "dispatch"}
        if event_tokens & call_names or any(
            isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))
            and any(token in node.name.casefold() for token in event_tokens)
            for node in nodes
        ):
            observations.append(ParadigmObservation(
                "event_driven", ("event, callback, emit, subscribe, or dispatch symbol",), 0.75
            ))
        if any(token in item for item in imports for token in ("rx", "reactivex", "reactor")) or "subscribe" in call_names:
            observations.append(ParadigmObservation(
                "reactive", ("reactive library import or subscribe call",), 0.85
            ))
        if {"typing", "Generic", "TypeVar"} & imports or any(
            isinstance(node, ast.Call) and isinstance(node.func, ast.Name) and node.func.id in {"TypeVar", "Generic"}
            for node in nodes
        ):
            observations.append(ParadigmObservation(
                "generic", ("typing, TypeVar, or Generic declaration",), 0.8
            ))
        if classes and any(isinstance(node, ast.Call) for node in nodes):
            observations.append(ParadigmObservation(
                "composition", ("objects are constructed and used through calls",), 0.55
            ))
        if any(isinstance(node, ast.Tuple) for node in nodes) or any(
            isinstance(node, ast.Call) and isinstance(node.func, ast.Name)
            and node.func.id in {"tuple", "frozenset"}
            for node in nodes
        ):
            observations.append(ParadigmObservation(
                "immutability", ("tuple, tuple conversion, or frozenset",), 0.7
            ))
        if any(
            isinstance(node, ast.Name) and node.id.startswith("_")
            for node in nodes
        ) or any(isinstance(node, ast.FunctionDef) and node.name.startswith("_") for node in nodes):
            observations.append(ParadigmObservation(
                "encapsulation", ("private-style name or internal method",), 0.6
            ))
        if any(
            isinstance(node, ast.Name) and node.id in {"ABC", "abstractmethod"}
            for node in nodes
        ) or "abc" in imports:
            observations.append(ParadigmObservation(
                "abstraction", ("abstract base class or abstractmethod evidence",), 0.85
            ))
        if any(class_node.bases for class_node in classes):
            observations.append(ParadigmObservation(
                "inheritance", ("class bases",), 0.95
            ))
            if len(classes) > 1:
                observations.append(ParadigmObservation(
                    "polymorphism", ("multiple classes in an inheritance hierarchy",), 0.65
                ))
        return observations

    def analyze_oop(self, source: str, language: str = "python") -> OopAnalysis:
        observations = tuple(self.recognize_paradigms(source, language))
        if language.casefold() != "python":
            return OopAnalysis(observations, ())
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return OopAnalysis((), ())
        nodes = list(ast.walk(tree))
        classes = [node for node in nodes if isinstance(node, ast.ClassDef)]
        functions = [node for node in nodes if isinstance(node, ast.FunctionDef)]
        findings: list[OopFinding] = []
        imports = {
            alias.name.casefold() for node in nodes
            if isinstance(node, (ast.Import, ast.ImportFrom))
            for alias in node.names
        }
        if len(classes) > 1 and any(class_node.bases for class_node in classes):
            findings.append(OopFinding(
                "solid_liskov",
                "review",
                "An inheritance hierarchy is present; substitutability cannot be proven statically.",
                "Prefer a narrow common contract and verify that every subtype preserves base preconditions and postconditions.",
                ("multiple classes", "class bases"),
            ))
        for class_node in classes:
            methods = [
                node for node in class_node.body
                if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))
            ]
            public_methods = [node for node in methods if not node.name.startswith("_")]
            method_names = {node.name for node in methods}
            if len(public_methods) >= 8:
                findings.append(OopFinding(
                    "solid_single_responsibility",
                    "violation",
                    f"Class {class_node.name} exposes many public operations and may have multiple responsibilities.",
                    "Split cohesive responsibilities into focused collaborators and keep the public interface small.",
                    (f"{class_node.name}: {len(public_methods)} public methods",),
                ))
            if len(methods) >= 6 and len({
                name for node in methods for name in self._attribute_names(node)
            }) >= 6:
                findings.append(OopFinding(
                    "cohesion",
                    "review",
                    f"Class {class_node.name} touches many unrelated attributes.",
                    "Group data and behavior that change together; extract collaborators for unrelated concerns.",
                    (f"{class_node.name}: {len(methods)} methods",),
                ))
            if any(
                isinstance(node, ast.Assign)
                and any(isinstance(target, ast.Attribute) and isinstance(target.value, ast.Name)
                        and target.value.id == "self" for target in node.targets)
                for node in ast.walk(class_node)
            ) and any(
                isinstance(node, ast.Call)
                and isinstance(node.func, ast.Name)
                and (
                    node.func.id in {"open", "connect", "request", "get"}
                    or node.func.id.endswith(("Client", "Repository", "Adapter", "Gateway"))
                )
                for node in ast.walk(class_node)
            ):
                findings.append(OopFinding(
                    "solid_dependency_inversion",
                    "review",
                    f"Class {class_node.name} owns state and directly creates or calls infrastructure.",
                    "Inject an abstraction for the external service so policy depends on a stable contract.",
                    (f"{class_node.name}: state plus infrastructure call",),
                ))
            if any(
                isinstance(base, ast.Name) and base.id.endswith("Mixin")
                for base in class_node.bases
            ) or class_node.name.endswith("Mixin"):
                findings.append(OopFinding(
                    "traits",
                    "observed",
                    f"Class {class_node.name} uses a mixin-style reuse mechanism.",
                    "Keep mixins small and stateless; use composition when behavior has independent lifecycle or dependencies.",
                    (f"{class_node.name}: mixin naming",),
                ))
        constructor = next(
            (node for node in functions if node.name == "__init__"),
            None,
        )
        if constructor is not None and len(constructor.args.args) >= 2:
            findings.append(OopFinding(
                "dependency_injection",
                "observed",
                "A constructor accepts collaborators instead of creating every dependency internally.",
                "Keep dependencies explicit and type their contracts; avoid service locator lookups.",
                ("__init__ has collaborator parameters",),
            ))
        if any(isinstance(node, ast.Assert) for node in nodes) or any(
            isinstance(node, ast.Raise) and any(
                isinstance(child, ast.Name) and child.id in {"ValueError", "TypeError"}
                for child in ast.walk(node)
            )
            for node in nodes
        ):
            findings.append(OopFinding(
                "design_by_contract",
                "observed",
                "The code checks preconditions or rejects invalid states.",
                "Make preconditions, postconditions, and invariants explicit at the public boundary.",
                ("assert or validation exception",),
            ))
        if len(classes) == 1 and len(functions) > 10:
            findings.append(OopFinding(
                "kiss",
                "review",
                "A single module combines a large number of operations around one class.",
                "Prefer the simplest decomposition that preserves one reason to change per component.",
                (f"{classes[0].name}: {len(functions)} functions",),
            ))
        external_names = {
            node.id for node in nodes
            if isinstance(node, ast.Name)
            and node.id not in {class_node.name for class_node in classes}
        }
        if classes and len(external_names) > 20:
            findings.append(OopFinding(
                "coupling",
                "review",
                "The module references many external names, indicating potentially high coupling.",
                "Introduce stable interfaces and isolate infrastructure behind adapters.",
                (f"{len(external_names)} external names",),
            ))
        if any(
            isinstance(node, ast.FunctionDef) and len(node.args.args) >= 7
            for node in nodes
        ):
            findings.append(OopFinding(
                "solid_interface_segregation",
                "review",
                "A callable receives many arguments and may represent a broad interface.",
                "Split role-specific contracts or pass a cohesive value object.",
                ("callable with seven or more parameters",),
            ))
        return OopAnalysis(observations, tuple(findings))

    def recognize_patterns(self, source: str, language: str = "python") -> list[DesignPatternObservation]:
        if language.casefold() != "python":
            return []
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return []
        nodes = list(ast.walk(tree))
        classes = [node for node in nodes if isinstance(node, ast.ClassDef)]
        functions = [node for node in nodes if isinstance(node, ast.FunctionDef)]
        calls = [node for node in nodes if isinstance(node, ast.Call)]
        class_names = {node.name for node in classes}
        call_names = {
            node.func.attr if isinstance(node.func, ast.Attribute) else node.func.id
            for node in calls if isinstance(node.func, (ast.Attribute, ast.Name))
        }
        observations: list[DesignPatternObservation] = []

        def add(pattern: str, evidence: tuple[str, ...], confidence: float = 0.7) -> None:
            observations.append(DesignPatternObservation(pattern, evidence, confidence))

        if any(node.name in {"create", "make", "factory"} for node in functions) or any(
            node.name.endswith(("Factory", "Creator")) for node in classes
        ):
            add("factory", ("creator/factory naming",), 0.8)
        if sum(
            1 for node in classes
            if node.name.endswith(("Factory", "FactoryBase"))
        ) >= 2:
            add("abstract_factory", ("multiple factory abstractions",), 0.7)
        if any(node.name in {"build", "construct"} for node in functions) or any(
            node.name.endswith("Builder") for node in classes
        ):
            add("builder", ("builder naming or build method",), 0.8)
        if any(node.name == "__new__" for node in functions) or any(
            node.name in {"get_instance", "instance"} for node in functions
        ):
            add("singleton", ("controlled instance creation",), 0.8)
        if any(node.name.endswith(("Adapter", "Wrapper")) for node in classes):
            add("adapter", ("adapter/wrapper naming",), 0.8)
        if any(node.name.endswith("Decorator") for node in classes) or any(
            node.name in {"wrap", "decorate"} for node in functions
        ):
            add("decorator", ("decorator/wrap naming",), 0.8)
        if any(node.name.endswith(("Facade", "Gateway")) for node in classes):
            add("facade", ("facade/gateway naming",), 0.8)
        if any(node.name.endswith("Proxy") for node in classes):
            add("proxy", ("proxy naming",), 0.8)
        if (
            any(node.name in {"execute", "apply", "select_strategy"} for node in functions)
            and len(classes) > 1
        ) or any(
            isinstance(node, ast.Attribute) and node.attr in {"strategy", "policy"}
            for node in nodes
        ):
            add("strategy", ("strategy/policy collaborator or common operation across classes",), 0.65)
        if any(node.name in {"notify", "subscribe", "attach", "detach"} for node in functions):
            add("observer", ("subscription/notification operations",), 0.85)
        if any(node.name in {"execute", "undo", "redo"} for node in functions) and any(
            node.name.endswith("Command") for node in classes
        ):
            add("command", ("command object with execute/undo",), 0.9)
        if any(node.name in {"transition", "handle_event"} for node in functions) and any(
            node.name.endswith("State") for node in classes
        ):
            add("state", ("state object with transition handling",), 0.85)
        if any(node.name.endswith(("Repository", "Store")) for node in classes):
            add("repository", ("repository/store naming",), 0.8)
        if any(node.name.endswith("Service") for node in classes):
            add("service", ("service naming",), 0.75)
        if any(
            isinstance(node, ast.FunctionDef)
            and node.name == "__init__"
            and len(node.args.args) >= 2
            for node in nodes
        ):
            add("dependency_injection", ("constructor receives collaborator",), 0.8)
        return observations

    def analyze_patterns(self, source: str, language: str = "python") -> DesignPatternAnalysis:
        observations = tuple(self.recognize_patterns(source, language))
        if language.casefold() != "python":
            return DesignPatternAnalysis(observations, ())
        try:
            tree = ast.parse(source)
        except SyntaxError:
            return DesignPatternAnalysis((), ())
        classes = [node for node in ast.walk(tree) if isinstance(node, ast.ClassDef)]
        functions = [node for node in ast.walk(tree) if isinstance(node, ast.FunctionDef)]
        recommendations: list[PatternRecommendation] = []
        class_count = len(classes)
        if class_count == 1 and len(functions) <= 3 and not observations:
            recommendations.append(PatternRecommendation(
                "none",
                "avoid",
                "The code is small and has no evidence requiring a design pattern.",
                "Adding a pattern would increase indirection without a demonstrated benefit.",
                ("one small class/module",),
            ))
        if class_count >= 3 and not any(item.pattern_id in {"strategy", "factory", "facade"} for item in observations):
            recommendations.append(PatternRecommendation(
                "strategy",
                "candidate",
                "Several classes exist but no interchangeable behavior is evident yet.",
                "Introduce Strategy only if callers need to select interchangeable algorithms; otherwise keep the simpler design.",
                (f"{class_count} classes",),
            ))
        if any(item.pattern_id == "singleton" for item in observations):
            recommendations.append(PatternRecommendation(
                "singleton",
                "review",
                "Global instance control can hide dependencies and make tests order-dependent.",
                "Prefer dependency injection unless one process-wide resource is an explicit invariant.",
                ("controlled instance creation",),
            ))
        if class_count >= 5 and any(item.pattern_id == "service" for item in observations):
            recommendations.append(PatternRecommendation(
                "service",
                "review",
                "A service layer with many collaborators may be accumulating orchestration responsibilities.",
                "Keep services cohesive and extract a pattern only when a repeated variation or boundary is evidenced.",
                ("many classes", "service naming"),
            ))
        return DesignPatternAnalysis(observations, tuple(recommendations))

    def recognize_architecture(
        self,
        project: Mapping[str, str] | str,
        language: str = "python",
    ) -> list[ArchitectureObservation]:
        """Recognize architecture only from supplied project paths and content."""
        if language.casefold() != "python":
            return []
        files = self._project_files(project)
        paths = tuple(path.replace("\\", "/").casefold() for path in files)
        content = "\n".join(files.values())
        lower_content = content.casefold()
        observations: list[ArchitectureObservation] = []

        def matching_paths(*parts: str) -> tuple[str, ...]:
            return tuple(
                path for path in paths
                if any(part in path.split("/") for part in parts)
            )

        def add(
            architecture_id: str,
            evidence: tuple[str, ...],
            confidence: float,
        ) -> None:
            observations.append(ArchitectureObservation(
                architecture_id, evidence, confidence
            ))

        service_dirs = matching_paths("services", "microservices")
        deployable_service_paths = tuple(
            path for path in paths
            if path.startswith(("services/", "microservices/"))
        )
        module_dirs = matching_paths("modules", "bounded_contexts", "contexts")
        layer_dirs = matching_paths("controllers", "services", "repositories", "models")
        hexagonal_dirs = matching_paths("ports", "adapters")
        clean_dirs = matching_paths("domain", "application", "infrastructure", "use_cases")
        mvc_dirs = matching_paths("controllers", "models", "views", "templates")
        cqrs_dirs = matching_paths("commands", "queries", "read_models", "write_models")
        ddd_dirs = matching_paths("aggregates", "value_objects", "domain_events")

        if len(service_dirs) >= 2 or any(
            marker in lower_content
            for marker in ("docker-compose", "kubernetes", "service discovery", "service_registry")
        ) and len(files) >= 3:
            add("microservices", (
                f"{len(service_dirs)} service-oriented paths",
                "deployment/discovery evidence" if "docker" in lower_content or "kubernetes" in lower_content
                else "multiple deployable service signals",
            ), 0.8)
        if service_dirs and any(
            marker in lower_content for marker in ("soap", "wsdl", "service bus", "enterprise service")
        ):
            add("soa", ("service paths", "enterprise service contract evidence"), 0.78)
        if any(
            marker in lower_content
            for marker in ("eventhandler", "event_handler", "subscriber", "publisher", "publish(", "subscribe(")
        ) or any(part in path for path in paths for part in ("events/", "listeners/", "consumers/")):
            add("event_driven", ("event publication/subscription or handler evidence",), 0.8)
        if len(layer_dirs) >= 3:
            add("layered", (
                "controller/service/repository/model layer paths",
                f"{len(layer_dirs)} layered path signals",
            ), 0.86)
        if len(hexagonal_dirs) >= 2:
            add("hexagonal", (
                "ports and adapters paths",
                "explicit boundary vocabulary",
            ), 0.9)
        if len(clean_dirs) >= 3:
            add("clean", (
                "domain/application/infrastructure boundary paths",
                "use-case or dependency-direction evidence",
            ), 0.86)
        if len(mvc_dirs) >= 3:
            add("mvc", (
                "controllers/models/views or templates paths",
                "web presentation boundary evidence",
            ), 0.84)
        if len(cqrs_dirs) >= 2 or (
            "commandbus" in lower_content and "querybus" in lower_content
        ):
            add("cqrs", (
                "separate command/query paths or buses",
                "read/write responsibility split",
            ), 0.88)
        if len(ddd_dirs) >= 2 or (
            any(part == "domain" for path in paths for part in path.split("/"))
            and any(part in path for path in paths for part in ("entities", "repositories"))
        ) or any(
            term in lower_content for term in ("bounded context", "aggregate root", "value object")
        ):
            add("ddd", (
                "domain modeling vocabulary and paths",
                "aggregate/value-object/bounded-context evidence",
            ), 0.82)
        if (
            any(part in path for path in paths for part in ("client/", "frontend/", "backend/", "server/"))
            or ("httpclient" in lower_content and "api" in lower_content)
        ):
            add("client_server", ("client/server or frontend/backend boundary evidence",), 0.78)
        if (
            len(deployable_service_paths) >= 2
            or any(term in lower_content for term in ("message queue", "kafka", "rabbitmq", "grpc"))
            or any(path.endswith(("docker-compose.yml", "deployment.yaml")) for path in paths)
        ):
            add("distributed_systems", ("networked service, messaging, or deployment evidence",), 0.72)

        if not any(item.architecture_id in {"microservices", "soa"} for item in observations):
            if len(module_dirs) >= 2:
                add("modular_monolith", (
                    "multiple module/bounded-context paths",
                    "no independent service deployment evidence",
                ), 0.78)
                add("monolith", (
                    "one supplied deployment boundary",
                    "modules are internal paths rather than independent services",
                ), 0.55)
            elif files:
                add("monolith", (
                    "single supplied application boundary",
                    "no independent service topology observed",
                ), 0.55)
        return observations

    def analyze_architecture(
        self,
        project: Mapping[str, str] | str,
        language: str = "python",
    ) -> ArchitectureAnalysis:
        observations = tuple(self.recognize_architecture(project, language))
        primary = None
        if observations:
            priority = {
                "microservices": 100, "soa": 95, "distributed_systems": 90,
                "clean": 80, "hexagonal": 79, "ddd": 78, "cqrs": 77,
                "event_driven": 76, "modular_monolith": 70, "layered": 65,
                "mvc": 64, "client_server": 63, "monolith": 10,
            }
            primary = max(
                observations,
                key=lambda item: (priority.get(item.architecture_id, 0), item.confidence),
            ).architecture_id
        return ArchitectureAnalysis(observations, primary)

    def explain_protocol(self, protocol_id: str) -> dict[str, Any]:
        profile = self.protocol_profiles().get(protocol_id)
        if profile is None:
            raise KeyError(f"unknown API protocol: {protocol_id}")
        return {"id": protocol_id, **profile}

    def recognize_protocols(
        self,
        project: Mapping[str, str] | str,
        language: str = "python",
    ) -> list[ProtocolObservation]:
        if language.casefold() != "python":
            return []
        files = self._project_files(project)
        paths = tuple(path.replace("\\", "/").casefold() for path in files)
        content = "\n".join(files.values())
        lower_content = content.casefold()
        observations: list[ProtocolObservation] = []

        def add(protocol_id: str, evidence: tuple[str, ...], confidence: float) -> None:
            observations.append(ProtocolObservation(protocol_id, evidence, confidence))

        if any(term in lower_content for term in ("http://", "httpclient", "requests.get", "fastapi", "flask", "@app.get", "@app.post")):
            add("http", ("HTTP URL or HTTP client/server evidence",), 0.9)
        if any(term in lower_content for term in ("@app.get", "@app.post", "get /", "post /", "put /", "delete /")):
            add("rest", ("HTTP resource methods or route evidence",), 0.82)
        if "https://" in lower_content or "wss://" in lower_content or any(term in lower_content for term in ("sslcontext", "tls", "httpsession")):
            add("https", ("HTTPS/TLS evidence",), 0.9)
        if any(term in lower_content for term in ("json", "json.loads", "json.dumps", "application/json")):
            add("json", ("JSON serialization or media type evidence",), 0.9)
        if any(term in lower_content for term in ("xml", "xml.etree", "application/xml")):
            add("xml", ("XML parser or media type evidence",), 0.9)
        if any(term in lower_content for term in ("websocket", "web_socket", "ws://", "wss://")):
            add("websockets", ("WebSocket endpoint or client evidence",), 0.9)
        if any(term in lower_content for term in ("server-sent events", "text/event-stream", "eventsource", "sse")):
            add("sse", ("SSE/EventSource or text/event-stream evidence",), 0.86)
        if "graphql" in lower_content or any(term in lower_content for term in ("query {", "mutation {", "resolvers")):
            add("graphql", ("GraphQL schema/query/resolver evidence",), 0.88)
        if any(term in lower_content for term in ("oauth", "authorization_code", "client_credentials")):
            add("oauth", ("OAuth flow or grant evidence",), 0.88)
        if any(term in lower_content for term in ("jwt", "jsonwebtoken", "jwt.encode", "jwt.decode")):
            add("jwt", ("JWT signing or validation evidence",), 0.9)
        if any(term in lower_content for term in ("set-cookie", "cookie", "request.cookies", "response.cookies")):
            add("cookies", ("cookie read/write evidence",), 0.86)
        if any(term in lower_content for term in ("session", "request.session", "sessionmiddleware")):
            add("sessions", ("server/session middleware evidence",), 0.84)
        if any(term in lower_content for term in ("authorization:", "content-type", "x-csrf-token", "headers")):
            add("headers", ("HTTP header access or declaration evidence",), 0.82)
        if any(term in lower_content for term in ("status_code", "http_200_ok", "http_401_unauthorized", "http_403_forbidden", "http_404_not_found")):
            add("status_codes", ("HTTP status code evidence",), 0.86)
        if any(term in lower_content for term in ("access-control-allow-origin", "allow_origins", "corsmiddleware", "cors")):
            add("cors", ("CORS header or middleware evidence",), 0.9)
        if any(term in lower_content for term in ("csrf", "xsrf", "csrfmiddleware", "x-csrf-token")):
            add("csrf", ("CSRF token or middleware evidence",), 0.9)
        if any(term in lower_content for term in ("rate limit", "ratelimit", "rate_limiter", "too_many_requests", "429")):
            add("rate_limiting", ("rate-limit policy or 429 evidence",), 0.88)
        if not observations and paths:
            add("http", ("project supplied without a more specific protocol signal",), 0.35)
        return observations

    def diagnose_communication(
        self,
        trace: Mapping[str, Any],
    ) -> tuple[CommunicationDiagnosis, ...]:
        diagnoses: list[CommunicationDiagnosis] = []
        status = trace.get("status")
        request_headers = {
            str(key).casefold(): str(value)
            for key, value in dict(trace.get("request_headers", {})).items()
        }
        response_headers = {
            str(key).casefold(): str(value)
            for key, value in dict(trace.get("response_headers", {})).items()
        }
        request_origin = trace.get("origin")
        allow_origin = response_headers.get("access-control-allow-origin")
        if trace.get("cross_origin") and allow_origin not in {request_origin, "*"}:
            diagnoses.append(CommunicationDiagnosis(
                "cors_origin_rejected", "high",
                "The response does not allow the requesting origin.",
                "Configure the backend allow-list for the exact frontend origin and handle OPTIONS.",
                (f"origin={request_origin}", f"allow-origin={allow_origin or 'missing'}"),
            ))
        if status in {401, 403} and not any(
            key in request_headers for key in ("authorization", "cookie", "x-csrf-token")
        ):
            diagnoses.append(CommunicationDiagnosis(
                "authentication_or_csrf_missing", "high",
                "The request lacks an observable authentication or CSRF credential.",
                "Send the credential required by the endpoint and verify its expected transport.",
                (f"status={status}", "authorization/cookie/csrf header missing"),
            ))
        if status == 419 or (trace.get("csrf_required") and "x-csrf-token" not in request_headers):
            diagnoses.append(CommunicationDiagnosis(
                "csrf_token_missing", "high",
                "The backend requires a CSRF token that is absent from the request.",
                "Obtain the token through the application flow and send the expected CSRF header or form field.",
                (f"status={status}", "csrf_required or 419 response"),
            ))
        if status == 429:
            diagnoses.append(CommunicationDiagnosis(
                "rate_limit_exceeded", "medium",
                "The backend rejected the request because its rate limit was exceeded.",
                "Honor Retry-After, reduce request frequency, and inspect client retry/backoff behavior.",
                ("status=429",),
            ))
        if status == 415 and "content-type" not in request_headers:
            diagnoses.append(CommunicationDiagnosis(
                "missing_content_type", "medium",
                "The backend rejected the representation because its media type is not declared.",
                "Send the media type expected by the endpoint, such as application/json.",
                ("status=415", "content-type header missing"),
            ))
        if status in {502, 503, 504}:
            diagnoses.append(CommunicationDiagnosis(
                "upstream_or_availability_failure", "high",
                "A gateway or service could not obtain a valid backend response.",
                "Trace the request across the gateway and backend, then verify timeout and service health evidence.",
                (f"status={status}",),
            ))
        if trace.get("expected_json") and "application/json" not in response_headers.get("content-type", "").casefold():
            diagnoses.append(CommunicationDiagnosis(
                "response_media_type_mismatch", "medium",
                "The frontend expects JSON but the response does not declare JSON.",
                "Return application/json consistently or update the client contract.",
                ("expected_json=true", f"content-type={response_headers.get('content-type', 'missing')}"),
            ))
        return tuple(diagnoses)

    def explain_database(self, database_id: str) -> dict[str, Any]:
        profile = self.database_profiles().get(database_id)
        if profile is None:
            raise KeyError(f"unknown database concept: {database_id}")
        return {"id": database_id, **profile}

    def analyze_database(
        self,
        project: Mapping[str, str] | str,
        language: str = "python",
    ) -> DatabaseAnalysis:
        files = self._project_files(project)
        paths = tuple(path.replace("\\", "/").casefold() for path in files)
        content = "\n".join(files.values())
        lower = content.casefold()
        observations: list[DatabaseObservation] = []
        findings: list[DatabaseFinding] = []

        def observe(database_id: str, evidence: tuple[str, ...], confidence: float = 0.8) -> None:
            observations.append(DatabaseObservation(database_id, evidence, confidence))

        def finding(
            issue: str,
            category: str,
            severity: str,
            explanation: str,
            correction: str,
            evidence: tuple[str, ...],
        ) -> None:
            findings.append(DatabaseFinding(
                issue, category, severity, explanation, correction, evidence
            ))

        if re.search(r"\b(select|insert|update|delete|create\s+table|alter\s+table)\b", lower):
            observe("sql", ("SQL statement keyword",), 0.95)
        if any(term in lower for term in ("mysql", "mysql+pymysql", "mysqli", "inno_db")):
            observe("mysql", ("MySQL driver or InnoDB evidence",), 0.9)
        if any(term in lower for term in ("postgres", "postgresql", "psycopg", "serial primary key")):
            observe("postgresql", ("PostgreSQL driver or syntax evidence",), 0.9)
        if any(term in lower for term in ("sqlite", "sqlite3")):
            observe("sqlite", ("SQLite driver or database URL evidence",), 0.9)
        if re.search(r"\bprimary\s+key\b", lower):
            observe("primary_keys", ("PRIMARY KEY constraint",), 0.95)
        if re.search(r"\bforeign\s+key\b|\breferences\s+\w+", lower):
            observe("foreign_keys", ("FOREIGN KEY or REFERENCES constraint",), 0.95)
        if re.search(r"\b(constraint|unique|check|not\s+null)\b", lower):
            observe("constraints", ("schema constraint declaration",), 0.85)
        if re.search(r"\bjoin\b", lower):
            observe("joins", ("JOIN clause",), 0.95)
        if re.search(r"\b(begin|commit|rollback|transaction)\b", lower):
            observe("transactions", ("transaction boundary keyword or API",), 0.9)
        if any(term in lower for term in ("serializable", "repeatable read", "read committed", "read uncommitted")):
            observe("isolation", ("transaction isolation level",), 0.9)
        if any(term in lower for term in ("normalize", "normal form", "1nf", "2nf", "3nf")):
            observe("normalization", ("normalization terminology",), 0.8)
        if any(term in lower for term in ("denormalize", "denormalized", "materialized view")):
            observe("denormalization", ("denormalized or materialized read model",), 0.82)
        if any("migration" in path or "migrations" in path for path in paths):
            observe("migrations", ("migration path or file",), 0.9)
        if any("seeder" in path or "seed" in path for path in paths):
            observe("seeders", ("seeder path or file",), 0.9)
        if any(term in lower for term in ("sqlalchemy", "django.db", "eloquent", "prisma", "sequelize", "active record")):
            observe("orm", ("ORM library or Active Record evidence",), 0.9)

        sql_statements = re.findall(
            r"\b(?:select|insert\s+into|update|delete\s+from)\b[\s\S]*?(?=;|$)",
            lower,
        )
        for statement in sql_statements:
            compact = " ".join(statement.split())
            if re.search(r"\bselect\s+\*", compact):
                finding(
                    "select_star", "performance", "medium",
                    "The query returns every column, increasing transfer and materialization cost.",
                    "Select only the columns required by the caller and preserve a stable projection.",
                    (compact[:180],),
                )
            if re.match(r"select\b", compact) and " limit " not in f" {compact} ":
                finding(
                    "unbounded_read", "performance", "medium",
                    "The query has no visible filter or limit and may scan or return an unbounded result.",
                    "Add a justified predicate and pagination or an explicit bounded contract.",
                    (compact[:180],),
                )
            if re.match(r"(update|delete)\b", compact) and " where " not in f" {compact} ":
                finding(
                    "write_without_predicate", "integrity", "high",
                    "The write statement has no visible WHERE predicate and can affect every row.",
                    "Require a tested predicate and guard destructive operations with an explicit transaction.",
                    (compact[:180],),
                )
            if " join " in f" {compact} " and " on " not in f" {compact} ":
                finding(
                    "join_without_on", "performance", "high",
                    "The JOIN has no visible ON condition and may create a Cartesian product.",
                    "Add the intended join key and verify the execution plan and cardinality.",
                    (compact[:180],),
                )
            if re.search(r"\b(where|join)\b[^;]*(?:lower|upper|date|cast)\s*\(", compact):
                finding(
                    "function_on_predicate_column", "performance", "medium",
                    "A function is applied inside a predicate and may prevent normal index use.",
                    "Use a sargable range, normalized value, or a matching functional index where supported.",
                    (compact[:180],),
                )

        if re.search(
            r"\bfor\b[\s\S]{0,300}?\.(?:objects|query|filter|where|find)"
            r"(?:\.[a-z_]+)*\s*\(",
            lower,
        ):
            observe("n_plus_one", ("query-like ORM call inside loop",), 0.9)
            finding(
                "n_plus_one", "performance", "high",
                "A database access appears inside a loop, which can issue one query per item.",
                "Eager-load the relation, batch the lookup, or replace the loop with a set-based query.",
                ("query-like ORM call inside loop",),
            )
        if "create_table" in lower and not re.search(r"primary\s+key|unique|not\s+null", lower):
            finding(
                "missing_integrity_constraints", "integrity", "medium",
                "A table definition has no visible primary, unique, or required-value constraint.",
                "Declare keys and invariants in the migration so the database enforces them.",
                ("create_table without visible integrity constraint",),
            )
        if "index" not in lower and re.search(r"\bwhere\b|\bjoin\b", lower) and sql_statements:
            finding(
                "unverified_index_support", "performance", "low",
                "Filtering or joining is present, but no index evidence was supplied.",
                "Inspect the execution plan and add indexes only for measured access patterns.",
                ("predicate/join present without index evidence",),
            )
        if any(item.category == "performance" for item in findings):
            observe("query_optimization", (
                "query performance finding requires plan/index/projection review",
            ), 0.78)
        if re.search(r"\b(insert|update|delete)\b", lower) and not re.search(
            r"\b(begin|transaction|atomic|unit_of_work)\b", lower
        ):
            finding(
                "write_without_transaction_boundary", "integrity", "medium",
                "A multi-step write context has no visible transaction boundary.",
                "Use an explicit transaction when several writes must commit atomically.",
                ("write operation without transaction marker",),
            )
        return DatabaseAnalysis(tuple(observations), tuple(findings))

    def explain_git(self, git_id: str) -> dict[str, Any]:
        profile = self.git_profiles().get(git_id)
        if profile is None:
            raise KeyError(f"unknown git concept: {git_id}")
        return {"id": git_id, **profile}

    def explain_testing(self, test_id: str) -> dict[str, Any]:
        profile = self.testing_profiles().get(test_id)
        if profile is None:
            raise KeyError(f"unknown testing concept: {test_id}")
        return {"id": test_id, **profile}

    def analyze_tests(
        self,
        project: Mapping[str, str] | str,
        language: str = "python",
    ) -> TestingAnalysis:
        if language.casefold() != "python":
            return TestingAnalysis((), ())
        files = self._project_files(project)
        observations: list[TestObservation] = []
        findings: list[TestFinding] = []

        def observe(test_id: str, evidence: tuple[str, ...], behavior: str, confidence: float = 0.8) -> None:
            observations.append(TestObservation(test_id, evidence, behavior, confidence))

        def finding(issue: str, category: str, severity: str, explanation: str, correction: str, evidence: tuple[str, ...]) -> None:
            findings.append(TestFinding(issue, category, severity, explanation, correction, evidence))

        for path, source in files.items():
            lower = source.casefold()
            path_lower = path.casefold()
            is_test = "test" in path_lower or "unittest" in lower or "pytest" in lower
            if not is_test:
                continue
            if path_lower.endswith((".feature", ".feature.md")):
                behavior = "; ".join(
                    line.strip().split(":", 1)[-1].strip()
                    for line in source.splitlines()
                    if line.strip().casefold().startswith(("scenario:", "given ", "when ", "then "))
                ) or "comportamiento descrito por el escenario"
                observe("end_to_end", (path,), behavior, 0.84)
                if any(term in lower for term in ("given ", "when ", "then ", "scenario:")):
                    observe("bdd", ("Given/When/Then or scenario vocabulary",), behavior, 0.9)
                continue
            try:
                tree = ast.parse(source)
            except SyntaxError:
                finding("invalid_test_source", "reliability", "high",
                        "The supplied test source cannot be parsed.", "Fix syntax before relying on the test.", (path,))
                continue
            calls = [
                node.func.attr if isinstance(node.func, ast.Attribute)
                else node.func.id if isinstance(node.func, ast.Name) else ""
                for node in ast.walk(tree) if isinstance(node, ast.Call)
            ]
            names = {node.name.casefold() for node in ast.walk(tree) if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef))}
            classes = [node for node in ast.walk(tree) if isinstance(node, ast.ClassDef)]
            assert_nodes = [node for node in ast.walk(tree) if isinstance(node, ast.Assert)]
            assert_calls = {name for name in calls if name.startswith("assert") or name in {"raises", "raises_regex"}}
            behavior_names = [
                node.name.replace("test_", "").replace("_", " ")
                for node in ast.walk(tree)
                if isinstance(node, (ast.FunctionDef, ast.AsyncFunctionDef)) and node.name.startswith("test")
            ]
            behavior = "; ".join(behavior_names) or "comportamiento cubierto por el archivo de prueba"
            if "pytest" in lower or "unittest" in lower:
                observe("unit_tests", (path,), behavior, 0.78)
            if any(marker in path_lower for marker in ("feature", "integration", "e2e", "end_to_end", "acceptance")):
                test_id = "end_to_end" if any(marker in path_lower for marker in ("e2e", "end_to_end", "acceptance")) else (
                    "integration_tests" if "integration" in path_lower else "feature_tests"
                )
                observe(test_id, (path,), behavior, 0.84)
            if assert_nodes or assert_calls:
                observe("assertions", (f"{len(assert_nodes) + len(assert_calls)} assertion signals",), behavior, 0.95)
            if any(name in calls for name in ("mock", "patch", "Mock", "MagicMock")) or "mock" in lower:
                observe("mocks", ("mock/patch usage",), behavior, 0.9)
            if any(term in lower for term in ("stub", "fake", "fixture", "pytest.fixture", "setUp", "tearDown")):
                test_id = "fixtures" if "fixture" in lower or "setup" in lower else (
                    "stubs" if "stub" in lower else "fakes"
                )
                observe(test_id, ("test setup or test double evidence",), behavior, 0.82)
            if "coverage" in lower or "--cov" in lower or "coverage.py" in lower:
                observe("coverage", ("coverage configuration or command",), behavior, 0.9)
            if any(term in lower for term in ("given(", "when(", "then(", "scenario", "feature:")):
                observe("bdd", ("Given/When/Then or scenario vocabulary",), behavior, 0.9)
            if "test_" in lower and any(name in lower for name in ("red", "green", "refactor", "tdd")):
                observe("tdd", ("TDD vocabulary in test source",), behavior, 0.7)
            if not (assert_nodes or assert_calls):
                finding("test_without_assertion", "reliability", "medium",
                        "The test file has no visible assertion signal.", "Add an assertion that verifies the intended behavior or explicitly document an interaction-only test.", (path,))
            if any(name in calls for name in ("mock", "patch", "Mock", "MagicMock")) and not assert_nodes and not assert_calls:
                finding("mock_without_behavior_assertion", "quality", "medium",
                        "The test uses a double but does not visibly assert its result or interaction.", "Assert the observable outcome or the expected call contract.", (path,))
        return TestingAnalysis(tuple(observations), tuple(findings))

    def explain_html(self, html_id: str) -> dict[str, Any]:
        profile = self.html_profiles().get(html_id)
        if profile is None:
            raise KeyError(f"unknown HTML concept: {html_id}")
        return {"id": html_id, **profile}

    def analyze_html(self, document: Mapping[str, str] | str) -> HtmlAnalysis:
        files = self._project_files(document)
        observations: list[HtmlObservation] = []
        findings: list[HtmlFinding] = []

        for path, source in files.items():
            if not (path.casefold().endswith((".html", ".htm")) or "<html" in source.casefold()):
                continue
            parser = _HtmlStructureParser()
            parser.feed(source)
            tags = parser.tags
            attrs = parser.attributes
            tag_names = {tag for tag, _ in tags}
            evidence = lambda tag: tuple(f"{path}:{line} <{tag}>" for found, line in tags if found == tag)
            if {"html", "head", "body"} <= tag_names:
                observations.append(HtmlObservation("document_structure", (f"{path}:html/head/body",), 0.98))
            if {"main", "header", "nav", "footer", "article", "section"} & tag_names:
                observations.append(HtmlObservation("semantic_html", tuple(f"<{tag}>" for tag in sorted(tag_names & {"main", "header", "nav", "footer", "article", "section"})), 0.9))
            if "form" in tag_names:
                observations.append(HtmlObservation("forms", evidence("form"), 0.95))
            if {"input", "select", "textarea", "button"} & tag_names:
                observations.append(HtmlObservation("inputs", tuple(f"<{tag}>" for tag in sorted(tag_names & {"input", "select", "textarea", "button"})), 0.95))
            if "table" in tag_names:
                observations.append(HtmlObservation("tables", evidence("table"), 0.95))
            if "a" in tag_names:
                observations.append(HtmlObservation("links", evidence("a"), 0.95))
            if {"img", "audio", "video", "picture"} & tag_names:
                observations.append(HtmlObservation("media", tuple(f"<{tag}>" for tag in sorted(tag_names & {"img", "audio", "video", "picture"})), 0.95))
            if any(name == "aria-label" or name.startswith("aria-") for values in attrs.values() for name in values):
                observations.append(HtmlObservation("aria", ("ARIA attributes present",), 0.9))
            if {"title", "meta"} & tag_names:
                observations.append(HtmlObservation("metadata", tuple(f"<{tag}>" for tag in sorted(tag_names & {"title", "meta"})), 0.9))
            if any(attr in source.casefold() for attr in ("navigator.", "localstorage", "sessionstorage", "geolocation", "canvas", "history.pushstate")):
                observations.append(HtmlObservation("html5_apis", ("HTML5 API reference",), 0.8))
            if "title" in tag_names and any(key.casefold() == "description" for key, value in parser.meta):
                observations.append(HtmlObservation("seo_basics", ("title and meta description",), 0.85))

            if "html" not in tag_names or "head" not in tag_names or "body" not in tag_names:
                findings.append(HtmlFinding("invalid_document_structure", "semantics", "high",
                    "The document does not expose the expected html, head and body structure.",
                    "Provide a complete HTML document structure.", (path,)))
            if "img" in tag_names:
                for tag, line in tags:
                    if tag == "img" and not attrs.get((tag, line), {}).get("alt"):
                        findings.append(HtmlFinding("image_without_alt", "accessibility", "high",
                            "An image has no non-empty alternative text.", "Add meaningful alt text, or alt=\"\" when the image is decorative.", (f"{path}:{line}",)))
            if "form" in tag_names:
                for tag, line in tags:
                    if tag == "input" and not attrs.get((tag, line), {}).get("name") and attrs.get((tag, line), {}).get("type", "text") != "hidden":
                        findings.append(HtmlFinding("input_without_name", "forms", "medium",
                            "A submitted input has no name and cannot contribute a named form value.", "Add a stable name attribute.", (f"{path}:{line}",)))
            if "table" in tag_names and "th" not in tag_names:
                findings.append(HtmlFinding("table_without_headers", "accessibility", "medium",
                    "The table has no header cells visible in the supplied markup.", "Use th elements with appropriate scope for tabular headers.", (path,)))
            if "title" not in tag_names:
                findings.append(HtmlFinding("missing_title", "seo", "medium",
                    "The document has no title element.", "Add a unique, descriptive title element.", (path,)))
            if "lang" not in parser.html_attributes:
                findings.append(HtmlFinding("missing_language", "accessibility", "medium",
                    "The html element does not declare a document language.", "Add a valid lang attribute to html.", (path,)))
            if "<a" in source.casefold() and any(tag == "a" and not attrs.get((tag, line), {}).get("href") for tag, line in tags):
                findings.append(HtmlFinding("link_without_href", "semantics", "low",
                    "An anchor lacks href and may not behave as a link.", "Use a button for an action or provide a valid href.", (path,)))
            if re.search(r"<button\b[^>]*>\s*</button\s*>", source, re.IGNORECASE):
                findings.append(HtmlFinding("empty_button", "accessibility", "medium",
                    "A button has no accessible text in the supplied markup.", "Provide visible text or an accessible name.", (path,)))
        return HtmlAnalysis(tuple(observations), tuple(findings))

    def explain_css(self, css_id: str) -> dict[str, Any]:
        profile = self.css_profiles().get(css_id)
        if profile is None:
            raise KeyError(f"unknown CSS concept: {css_id}")
        return {"id": css_id, **profile}

    def analyze_css(self, stylesheet: Mapping[str, str] | str) -> CssAnalysis:
        files = self._project_files(stylesheet)
        observations: list[CssObservation] = []
        findings: list[CssFinding] = []

        for path, source in files.items():
            if not (path.casefold().endswith((".css", ".scss", ".sass")) or "{" in source and "}" in source):
                continue
            lower = source.casefold()
            rules = re.findall(r"([^{}]+)\{([^{}]*)\}", source, re.DOTALL)
            selectors = [selector.strip() for selector, _ in rules if selector.strip() and not selector.strip().startswith("@")]
            declarations = [body for _, body in rules]
            def observe(css_id: str, evidence: tuple[str, ...], confidence: float = 0.9) -> None:
                observations.append(CssObservation(css_id, evidence, confidence))
            def finding(issue: str, category: str, severity: str, explanation: str, correction: str, evidence: tuple[str, ...]) -> None:
                findings.append(CssFinding(issue, category, severity, explanation, correction, evidence))

            if selectors:
                observe("selectors", tuple(selectors[:5]), 0.98)
                specificity = []
                for selector in selectors:
                    ids = len(re.findall(r"#[\w-]+", selector))
                    classes = len(re.findall(r"[.#:\[][\w-]+", selector)) - ids
                    elements = len(re.findall(r"(?<![#.:\w-])[a-zA-Z][\w-]*", selector))
                    specificity.append((ids, max(classes, 0), elements, selector))
                observe("specificity", tuple(f"{selector}: {a}-{b}-{c}" for a, b, c, selector in specificity[:5]), 0.82)
            if "!" in source and re.search(r"!\s*important\b", source, re.IGNORECASE):
                observe("cascade", ("!important declaration",), 0.95)
                finding("important_overuse", "cascade", "medium",
                        "!important can bypass normal cascade ordering and make overrides difficult.",
                        "Prefer scoped selectors, layers, or a deliberate specificity strategy.", (path,))
            if any("inherit" in body or "initial" in body or "unset" in body for body in declarations):
                observe("inheritance", ("inherit/initial/unset declaration",), 0.9)
            if "box-sizing" in lower or re.search(r"\b(width|height)\s*:[^;{}]+", lower):
                observe("box_model", ("width/height or box-sizing declaration",), 0.85)
            if re.search(r"\bdisplay\s*:\s*(flex|inline-flex)\b", lower):
                observe("flexbox", ("display:flex",), 0.98)
            if re.search(r"\bdisplay\s*:\s*(grid|inline-grid)\b", lower):
                observe("grid", ("display:grid",), 0.98)
            if re.search(r"\bdisplay\s*:\s*(block|inline|none|contents)\b", lower):
                observe("display", ("display declaration",), 0.95)
            if re.search(r"\bposition\s*:\s*(absolute|fixed|sticky|relative)\b", lower):
                observe("position", ("position declaration",), 0.95)
            if "@media" in lower:
                observe("media_queries", ("@media rule",), 0.98)
                observe("responsive_design", ("responsive breakpoint evidence",), 0.86)
            if "@keyframes" in lower:
                observe("animations", ("@keyframes rule",), 0.98)
            if re.search(r"\banimation\s*:", lower):
                observe("animations", ("animation declaration",), 0.95)
            if "transition" in lower:
                observe("transitions", ("transition declaration",), 0.95)
            if "::" in source:
                observe("pseudo_elements", ("double-colon pseudo-element",), 0.95)
            if re.search(r"--[\w-]+\s*:", source) and "var(" in lower:
                observe("variables", ("custom property and var() usage",), 0.98)
            if "&" in source and re.search(r"&[\w:.\[]", source):
                observe("nesting", ("nested selector using &",), 0.88)
            if re.search(r"\b(clamp|minmax|subgrid|:where\(|:is\(|container-type|color-mix)\b", lower):
                observe("modern_css", ("modern CSS function, selector, or container feature",), 0.9)
            if re.search(r"\b(width|height)\s*:\s*\d+(px|vh|vw)\b", lower) and "@media" not in lower:
                finding("fixed_layout_without_responsive_rule", "responsive_design", "medium",
                        "Fixed dimensions are present without a visible media query in the supplied stylesheet.",
                        "Use fluid units or add breakpoints based on the supported layout contract.", (path,))
            if re.search(r"\boverflow\s*:\s*hidden\b", lower) and re.search(r"\bposition\s*:\s*(absolute|fixed)\b", lower):
                finding("overflow_can_clip_positioned_content", "layout", "medium",
                        "Overflow clipping and positioned descendants can hide content or controls.",
                        "Confirm the clipping boundary and use a deliberate overflow strategy.", (path,))
            if selectors and max((a for a, _, _, _ in specificity), default=0) > 0:
                finding("id_selector_specificity", "specificity", "low",
                        "ID selectors create high specificity and can make component overrides brittle.",
                        "Prefer classes or cascade layers for reusable component styling.", (path,))
            if source.count("{") != source.count("}"):
                finding("unbalanced_braces", "syntax", "high",
                        "The stylesheet has unmatched braces and may not parse as intended.",
                        "Balance CSS blocks before diagnosing visual behavior.", (path,))
        return CssAnalysis(tuple(observations), tuple(findings))

    def explain_javascript(self, javascript_id: str) -> dict[str, Any]:
        profile = self.javascript_profiles().get(javascript_id)
        if profile is None:
            raise KeyError(f"unknown JavaScript concept: {javascript_id}")
        return {"id": javascript_id, **profile}

    def analyze_javascript(self, source: Mapping[str, str] | str) -> JavaScriptAnalysis:
        files = self._project_files(source)
        observations: list[JavaScriptObservation] = []
        findings: list[JavaScriptFinding] = []
        for path, code in files.items():
            if not (path.casefold().endswith((".js", ".mjs", ".cjs", ".jsx", ".ts", ".tsx")) or
                    re.search(r"\b(const|let|function|class|import|export)\b", code)):
                continue
            lower = code.casefold()
            lines = code.splitlines()

            def evidence(pattern: str, limit: int = 3) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(lines, 1)
                    if matcher.search(line)
                )[:limit]

            def observe(javascript_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(JavaScriptObservation(javascript_id, found, confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(JavaScriptFinding(issue, category, severity, explanation, correction, found))

            observe("variables", r"\b(var|let|const)\b", 0.98)
            if re.search(r"\bvar\b", lower):
                finding("function_scoped_var", "scope", "medium",
                        "var is function-scoped and can leak across blocks or be redeclared unexpectedly.",
                        "Prefer const by default and let when reassignment is required.", r"\bvar\b")
            observe("scope", r"\b(var|let|const)\b|=>", 0.85)
            observe("closures", r"=>|function\s*\([^)]*\)\s*\{", 0.82)
            observe("functions", r"\bfunction\b|=>", 0.98)
            observe("objects", r"\{[^{}]*:\s*[^{}]+\}", 0.88)
            observe("arrays", r"\[[^\]]*\]", 0.88)
            observe("classes", r"\bclass\s+\w+", 0.98)
            observe("prototypes", r"\.prototype\b|Object\.create\s*\(", 0.95)
            observe("modules", r"\b(import|export)\b", 0.98)
            observe("promises", r"\bPromise\b|\.then\s*\(|\.catch\s*\(|\bfetch\s*\(", 0.9)
            observe("async_await", r"\basync\b|\bawait\b", 0.98)
            observe("events", r"\baddEventListener\s*\(|\bon[A-Z]\w*\s*=", 0.95)
            observe("dom", r"\bdocument\.|\bquerySelector\s*\(|\bgetElementById\s*\(", 0.95)
            observe("fetch", r"\bfetch\s*\(", 0.98)
            observe("error_handling", r"\btry\s*\{|catch\s*\(|throw\b", 0.95)
            observe("destructuring", r"(?:const|let|var)\s*\{[^}]+\}\s*=|(?:const|let|var)\s*\[[^\]]+\]\s*=", 0.92)
            observe("spread", r"\.\.\.[A-Za-z_$]", 0.92)
            observe("iterators", r"\[Symbol\.iterator\]|\.next\s*\(", 0.9)
            observe("generators", r"\bfunction\s*\*|\byield\b", 0.98)
            observe("map", r"\bnew\s+Map\s*\(|\.set\s*\(|\.get\s*\(", 0.88)
            observe("set", r"\bnew\s+Set\s*\(", 0.98)

            if re.search(r"\bfetch\s*\(", lower) and not re.search(r"\b(catch|try)\b", lower):
                finding("fetch_without_error_handling", "async", "high",
                        "A fetch call is present without visible catch or try error handling.",
                        "Handle rejected promises and non-OK HTTP responses explicitly.", r"\bfetch\s*\(")
            if re.search(r"\basync\s+function\b|\basync\s*\(", lower) and "await" not in lower and "return" not in lower:
                finding("async_without_async_operation", "design", "low",
                        "An async function has no visible await or returned asynchronous operation.",
                        "Remove async or return the intended promise explicitly.", r"\basync\b")
            if re.search(r"\bfor\s*\([^)]*\)\s*\{[^{}]*\bawait\b", lower, re.DOTALL):
                finding("sequential_await_in_loop", "async", "medium",
                        "Await inside a loop may serialize independent operations.",
                        "Use Promise.all when operations are independent, while preserving ordering and limits when required.", r"\bfor\s*\([^)]*\)")
            if re.search(r"\.innerHTML\s*=", lower):
                finding("innerhtml_injection_risk", "security", "high",
                        "Assigning dynamic content to innerHTML can create an injection risk when the value is untrusted.",
                        "Prefer textContent or sanitize trusted HTML at a documented boundary.", r"\.innerHTML\s*=")
            if re.search(r"\baddEventListener\s*\(", lower) and re.search(r"\bsetInterval\s*\(", lower):
                finding("listener_or_timer_lifecycle_risk", "lifecycle", "medium",
                        "Event listeners and timers are registered with no visible cleanup evidence.",
                        "Remove listeners and clear timers when the owning component or view is disposed.", r"\b(addEventListener|setInterval)\b")
            if re.search(r"\bvar\b", lower) and re.search(r"\bfor\s*\(", lower):
                finding("loop_closure_var_risk", "scope", "medium",
                        "var in loop code can cause callbacks to observe a shared final value.",
                        "Use let for per-iteration bindings or capture the intended value explicitly.", r"\bvar\b")
            if code.count("{") != code.count("}"):
                finding("unbalanced_javascript_braces", "syntax", "high",
                        "The supplied JavaScript has unmatched braces and may not parse as intended.",
                        "Balance blocks before diagnosing runtime behavior.", r"[{}]")
        return JavaScriptAnalysis(tuple(observations), tuple(findings))

    def analyze_advanced_php(self, source: Mapping[str, str] | str) -> AdvancedPHPAnalysis:
        files = self._project_files(source)
        observations: list[AdvancedPHPObservation] = []
        findings: list[AdvancedPHPFinding] = []
        entries = [(path, code, code.casefold()) for path, code in files.items()]
        combined = "\n".join(f"{path}\n{code}" for path, code, _ in entries).casefold()

        def evidence(pattern: str, limit: int = 6) -> tuple[str, ...]:
            matcher = re.compile(pattern, re.IGNORECASE)
            result = []
            for path, code, _ in entries:
                for line_no, line in enumerate(code.splitlines(), 1):
                    if matcher.search(line):
                        result.append(f"{path}:{line_no}: {line.strip()}")
            return tuple(result[:limit])

        def observe(identifier: str, pattern: str, confidence: float = 0.9) -> None:
            found = evidence(pattern)
            if found:
                observations.append(AdvancedPHPObservation(identifier, found, confidence))

        def finding(issue: str, category: str, severity: str, explanation: str,
                    correction: str, pattern: str) -> None:
            found = evidence(pattern)
            if found:
                findings.append(AdvancedPHPFinding(issue, category, severity, explanation, correction, found))

        observe("memory", r"\b(?:memory_get_usage|memory_get_peak_usage|memory_limit|gc_collect_cycles|unset)\b")
        observe("references", r"(?:&\s*\$|function\s*&\s*\w+|foreach\s*\([^)]*&\s*\$)")
        observe("autoloading", r"\b(?:spl_autoload_register|__autoload|autoload\.php)\b")
        observe("composer", r"composer\.json|vendor/autoload\.php|composer\s+(?:install|require|dump-autoload)")
        observe("psr", r"\bPsr\\|psr-\d+|PSR-\d+")
        observe("spl", r"\bSpl(?:File|FixedArray|PriorityQueue|Queue|Stack|ObjectStorage|PriorityQueue)\b|\b(?:Iterator|ArrayIterator|RegexIterator)\b")
        observe("reflection", r"\bReflection(?:Class|Method|Property|Function|Attribute)\b")
        observe("attributes", r"#\[[\w\\]+")
        observe("serialization", r"\b(?:serialize|unserialize|__serialize|__unserialize)\s*\(")
        observe("streams", r"\b(?:fopen|fclose|stream_context_create|stream_get_contents|php://|stream_set_timeout)\b")
        observe("processes", r"\b(?:proc_open|proc_close|proc_terminate|shell_exec|exec|passthru|system)\s*\(")
        observe("cli", r"\b(?:STDIN|STDOUT|STDERR|php_sapi\s*\(\)|getopt\s*\(|\$argv\b|\$argc\b)\b")
        observe("environment", r"\b(?:getenv|putenv|php_ini_loaded_file|ini_get|ini_set|\$_ENV)\b")
        observe("performance", r"\b(?:microtime|hrtime|array_map|array_filter|iterator_to_array|usleep)\b")
        observe("opcache", r"\b(?:opcache_|OPcache|opcache\.enable|opcache_reset)\b")
        observe("error_handling", r"\b(?:set_error_handler|set_exception_handler|error_reporting|trigger_error|ErrorException)\b|@\s*(?:include|require|file_get_contents|unlink|fopen|call_user_func)")

        finding("reference_alias_leak", "references", "medium",
                "A reference variable is created and may keep an alias to storage beyond the intended loop or scope.",
                "Unset loop references and avoid returning mutable aliases unless the contract requires them.",
                r"foreach\s*\([^)]*&\s*\$|&\s*\$[A-Za-z_]\w*\s*=")
        finding("legacy_autoloading", "autoloading", "medium",
                "Legacy __autoload is present instead of the Composer-compatible autoload contract.",
                "Use spl_autoload_register or Composer PSR-4 autoloading and keep one authoritative loader.",
                r"\b__autoload\s*\(")
        finding("unsafe_process_execution", "processes", "critical",
                "A shell/process API receives interpolated or request-controlled input and may execute unintended commands.",
                "Use a fixed executable and argument list, validate inputs, and avoid shell interpretation.",
                r"\b(?:shell_exec|exec|passthru|system|proc_open)\s*\([^)]*\$_(?:GET|POST|REQUEST|COOKIE)")
        finding("unbounded_process", "processes", "high",
                "A child process is opened without visible timeout or termination handling.",
                "Configure non-blocking pipes, a timeout and deterministic proc_close/proc_terminate cleanup.",
                r"\bproc_open\s*\(")
        finding("unbounded_stream_read", "streams", "medium",
                "A stream is read without visible bounded length or timeout control.",
                "Use explicit read limits and stream timeouts for external or untrusted sources.",
                r"\b(?:file_get_contents|stream_get_contents)\s*\(")
        finding("serialization_boundary_risk", "serialization", "high",
                "Untrusted serialized data crosses an application boundary and can trigger object hydration behavior.",
                "Prefer JSON or restrict allowed classes after validating the trust boundary.",
                r"\bunserialize\s*\(")
        finding("environment_secret_exposure", "environment", "high",
                "Environment or configuration values are exposed through output or logging.",
                "Redact secrets and keep environment access behind a typed configuration boundary.",
                r"(?:var_dump|print_r|echo)\s*\([^)]*\$_ENV|(?:var_dump|print_r|echo)\s*\([^)]*getenv\s*\(")
        finding("opcache_disabled", "opcache", "medium",
                "OPcache is explicitly disabled, which can increase compile overhead in production.",
                "Confirm the deployment profile and enable OPcache where appropriate after measuring.",
                r"opcache\.enable\s*[=:]\s*0|opcache_disable\s*\(|ini_set\s*\(\s*['\"]opcache\.enable['\"]\s*,\s*0")
        finding("global_error_suppression", "error_handling", "medium",
                "The @ operator suppresses errors and can hide operational failures.",
                "Handle expected failures explicitly and preserve diagnostics for unexpected errors.",
                r"@\s*(?:include|require|file_get_contents|unlink|fopen|call_user_func)")
        finding("unbounded_memory_materialization", "performance", "medium",
                "A potentially large iterator or stream is materialized into an array.",
                "Process values incrementally or define a documented size bound.",
                r"\biterator_to_array\s*\(")
        if "composer.json" in combined and not re.search(r'"autoload"\s*:', combined):
            finding("composer_autoload_contract_missing", "composer", "medium",
                    "Composer metadata is present without a visible autoload section.",
                    "Declare PSR-4 or another explicit autoload mapping and regenerate the autoloader.",
                    r"composer\.json")
        return AdvancedPHPAnalysis(tuple(observations), tuple(findings))

    def analyze_php(self, source: Mapping[str, str] | str) -> PHPAnalysis:
        files = self._project_files(source)
        observations: list[PHPObservation] = []
        findings: list[PHPFinding] = []
        for path, code in files.items():
            if not (path.casefold().endswith(".php") or "<?php" in code or re.search(r"\b(namespace|class|function)\b", code)):
                continue
            lower = code.casefold()
            lines = code.splitlines()

            def evidence(pattern: str, limit: int = 5) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(lines, 1)
                    if matcher.search(line)
                )[:limit]

            def observe(php_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(PHPObservation(php_id, found, confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(PHPFinding(issue, category, severity, explanation, correction, found))

            observe("syntax", r"<\?php|declare\s*\(\s*strict_types|=>|::")
            observe("types", r"\b(?:int|float|string|bool|array|object|mixed|void|never|iterable)\b\s+\$")
            observe("functions", r"\bfunction\s+\w+\s*\(|\b(?:session_start|unserialize|password_hash|password_verify|hash|md5)\s*\(")
            observe("arrays", r"\barray\s*\(|\[[^\]]*(?:=>|,)[^\]]*\]|->(?:map|filter|reduce)\s*\(|\$_(?:GET|POST|REQUEST|COOKIE|SESSION)\b")
            observe("objects", r"\bnew\s+\w+|->\w+")
            observe("classes", r"\b(?:abstract\s+)?class\s+\w+")
            observe("interfaces", r"\binterface\s+\w+|\bimplements\s+")
            observe("traits", r"\btrait\s+\w+|\buse\s+\w+(?:,\s*\w+)*\s*;")
            observe("enums", r"\benum\s+\w+")
            observe("namespaces", r"\bnamespace\s+[\w\\]+|use\s+[\w\\]+")
            observe("exceptions", r"\b(?:try\s*\{|catch\s*\(|throw\s+new|finally\s*\{)")
            observe("attributes", r"#\[[\w\\]+")
            observe("closures", r"\bfunction\s*\([^)]*\)\s*(?::\s*[\w\\|?]+)?\s*\{|fn\s*\([^)]*\)\s*=>")
            observe("generators", r"\byield(?:\s+from)?\b")
            observe("iterators", r"\b(?:Iterator|IteratorAggregate|Generator|foreach\s*\(|yield(?:\s+from)?)")
            observe("dependency_injection", r"__construct\s*\([^)]*\b(?:private|protected|public|readonly)\b|container->(?:get|make)|inject")
            observe("reflection", r"\bReflection(?:Class|Method|Property|Function)\b|new\s+Reflection")
            observe("filesystem", r"\b(?:fopen|file_get_contents|file_put_contents|unlink|glob|scandir)(?:_once)?\s*\(|\b(?:include|require)(?:_once)?\s+")
            observe("sessions", r"\bsession_(?:start|regenerate_id|set_save_handler)\s*\(|\$_SESSION")
            observe("security", r"\b(?:password_hash|password_verify|hash_hmac|csrf|escape|htmlspecialchars|filter_input|password|md5|sha1|unserialize)\b")

            finding("dynamic_code_execution", "security", "critical",
                    "eval executes dynamic PHP code and can turn untrusted input into arbitrary code.",
                    "Remove eval and use explicit data-driven control flow or safe parsers.", r"\beval\s*\(")
            finding("unsafe_unserialize", "security", "high",
                    "unserialize can instantiate attacker-controlled objects when its input is not fully trusted.",
                    "Prefer JSON or constrain allowed_classes with a validated trust boundary.", r"\bunserialize\s*\(")
            finding("dynamic_file_path", "filesystem", "high",
                    "A filesystem or include path contains request-controlled input and may enable traversal or local file inclusion.",
                    "Use an allowlist of resolved paths and validate canonical paths before access.", r"(?:include|require)\s+\$_(?:GET|POST|REQUEST|COOKIE)|(?:file_get_contents|fopen|unlink)\s*\([^)]*\$_(?:GET|POST|REQUEST|COOKIE)")
            finding("sql_interpolation", "security", "high",
                    "User input is interpolated into a SQL string, which can enable SQL injection.",
                    "Use parameterized queries or the framework's bound query API.", r"(?:SELECT|INSERT|UPDATE|DELETE)[^;\n]*(?:\.\s*\$_|\{\$_)")
            finding("weak_password_storage", "security", "critical",
                    "A password is passed to a general-purpose hash or stored directly instead of password_hash.",
                    "Use password_hash and password_verify with an appropriate password algorithm.", r"(?:md5|sha1|hash\s*\([^)]*password|password\s*=\s*\$_)")
            finding("session_regeneration_missing", "sessions", "medium",
                    "A session is started but no regeneration evidence is visible near the authentication flow.",
                    "Regenerate the session ID after authentication and privilege changes.", r"\bsession_start\s*\(")
            finding("empty_exception_catch", "exceptions", "medium",
                    "An empty catch block can silently discard failures and make recovery impossible to diagnose.",
                    "Log or translate the exception and preserve the failure contract.", r"catch\s*\([^)]*\)\s*\{\s*\}")
            if "<?php" in code and "declare(strict_types=1)" not in lower:
                finding("strict_types_not_declared", "types", "low",
                        "The file has no visible strict_types declaration, so scalar coercion may hide contract errors.",
                        "Declare strict_types where the project contract expects strict scalar boundaries.", r"<\?php")
            if re.search(r"\b(?:password|secret|token)\b\s*=\s*['\"]", lower):
                finding("hardcoded_secret", "security", "high",
                        "A credential-like value is assigned as a literal in source.",
                        "Load secrets from a managed configuration boundary and rotate exposed credentials.", r"\b(?:password|secret|token)\b\s*=\s*['\"]")
        return PHPAnalysis(tuple(observations), tuple(findings))

    def explain_browser(self, browser_id: str) -> dict[str, Any]:
        profile = self.browser_profiles().get(browser_id)
        if profile is None:
            raise KeyError(f"unknown browser concept: {browser_id}")
        return {"id": browser_id, **profile}

    def analyze_browser(self, source: Mapping[str, str] | str) -> BrowserAnalysis:
        files = self._project_files(source)
        observations: list[BrowserObservation] = []
        findings: list[BrowserFinding] = []
        for path, code in files.items():
            lower = code.casefold()
            lines = code.splitlines()

            def evidence(pattern: str, limit: int = 3) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(lines, 1)
                    if matcher.search(line)
                )[:limit]

            def observe(browser_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(BrowserObservation(browser_id, found, confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(BrowserFinding(issue, category, severity, explanation, correction, found))

            observe("dom", r"\bdocument\b|querySelector|getElementById|MutationObserver", 0.95)
            observe("rendering", r"\brequestAnimationFrame\b|IntersectionObserver|ResizeObserver|canvas|getBoundingClientRect|innerHTML", 0.9)
            observe("event_loop", r"\bsetTimeout\b|\bsetInterval\b|queueMicrotask|Promise\.resolve|requestAnimationFrame", 0.9)
            observe("browser_storage", r"localStorage|sessionStorage|indexedDB", 0.98)
            observe("cookies", r"document\.cookie|Set-Cookie|credentials\s*:\s*['\"]include", 0.95)
            observe("local_storage", r"localStorage", 0.98)
            observe("session_storage", r"sessionStorage", 0.98)
            observe("fetch", r"\bfetch\s*\(", 0.98)
            observe("network_requests", r"\bfetch\s*\(|XMLHttpRequest|EventSource|WebSocket", 0.95)
            observe("caching", r"\bcaches\.(open|match|put|delete)|Cache-Control|serviceWorker|staleWhileRevalidate", 0.92)
            observe("cors", r"\b(origin|Access-Control-Allow-Origin|mode\s*:\s*['\"]cors|CORS)\b", 0.88)
            observe("browser_security", r"Content-Security-Policy|same-origin|csrf|XSS|DOMPurify|noopener|no-referrer|innerHTML|localStorage|sessionStorage", 0.8)
            observe("lifecycle", r"\bDOMContentLoaded\b|load\b|beforeunload|pagehide|visibilitychange|AbortController|addEventListener|setInterval|setTimeout", 0.93)

            if re.search(r"\blocalstorage\b|\bsessionstorage\b", lower) and re.search(r"\b(token|password|secret|authorization|jwt)\b", lower):
                finding("sensitive_data_in_web_storage", "security", "high",
                        "Sensitive-looking data is used near browser storage APIs, which are readable by same-origin JavaScript.",
                        "Avoid storing long-lived secrets in web storage; use an appropriate protected session design.", r"(localStorage|sessionStorage)")
            if "document.cookie" in lower and "httponly" not in lower:
                finding("client_cookie_cannot_be_httponly", "security", "medium",
                        "A script-accessible cookie reference cannot demonstrate HttpOnly protection.",
                        "Set security attributes server-side: HttpOnly, Secure and an appropriate SameSite policy.", r"document\.cookie")
            if re.search(r"\bfetch\s*\(", lower) and not re.search(r"\b(catch|try)\b", lower):
                finding("network_failure_not_handled", "network", "high",
                        "A browser network request has no visible rejection handling.",
                        "Handle rejected requests, aborts, timeouts and non-OK responses explicitly.", r"\bfetch\s*\(")
            if re.search(r"\bfetch\s*\([^)]*['\"]https?://", lower) and not re.search(r"\b(cors|mode\s*:\s*['\"]cors|credentials)\b", lower):
                finding("cross_origin_contract_not_explicit", "cors", "medium",
                        "An absolute cross-origin request is present without an explicit client-side CORS or credentials contract.",
                        "Confirm the server CORS policy and explicitly choose the required credentials mode.", r"\bfetch\s*\([^)]*https?://")
            if re.search(r"\bsetinterval\s*\(", lower) and not re.search(r"\bclearinterval\s*\(", lower):
                finding("interval_without_cleanup", "lifecycle", "medium",
                        "A repeating timer has no visible cleanup path.",
                        "Clear the interval when the page, component or feature is disposed.", r"\bsetInterval\s*\(")
            if re.search(r"\baddeventlistener\s*\(", lower) and not re.search(r"\bremoveeventlistener\s*\(", lower):
                finding("event_listener_without_cleanup", "lifecycle", "medium",
                        "An event listener is registered without visible removal or abort-signal cleanup.",
                        "Remove the listener or use an AbortController tied to the owning lifecycle.", r"\baddEventListener\s*\(")
            if re.search(r"\brequestanimationframe\s*\(", lower) and not re.search(r"\bcancelanimationframe\s*\(", lower):
                finding("animation_frame_without_cancel_path", "rendering", "low",
                        "A rendering loop has no visible cancellation path.",
                        "Cancel scheduled frames when the owning view is hidden or destroyed.", r"\brequestAnimationFrame\s*\(")
            if re.search(r"\binnerhtml\s*=", lower) and not re.search(r"\b(dompurify|sanitize)\b", lower):
                finding("unsafe_dom_rendering", "browser_security", "high",
                        "Dynamic HTML is assigned without visible sanitization evidence.",
                        "Use textContent for text or sanitize HTML at a trusted boundary.", r"\binnerHTML\s*=")
        return BrowserAnalysis(tuple(observations), tuple(findings))

    def explain_tailwind(self, tailwind_id: str) -> dict[str, Any]:
        profile = self.tailwind_profiles().get(tailwind_id)
        if profile is None:
            raise KeyError(f"unknown Tailwind concept: {tailwind_id}")
        return {"id": tailwind_id, **profile}

    def analyze_tailwind(self, source: Mapping[str, str] | str) -> TailwindAnalysis:
        files = self._project_files(source)
        observations: list[TailwindObservation] = []
        findings: list[TailwindFinding] = []
        for path, code in files.items():
            lower = code.casefold()
            class_values = re.findall(r"""(?:class|className)\s*=\s*["']([^"']+)["']""", code)
            class_tokens = [token for value in class_values for token in re.split(r"\s+", value.strip()) if token]
            classes = set(class_tokens)
            if not classes:
                classes = {
                    token for token in re.findall(r"(?<![\w-])(?:[a-z][\w-]*:)*-?[\w-]+(?:\[[^\]]+\])?", code)
                    if any(token.split(":")[-1].startswith(prefix) for prefix in (
                        "p-", "m-", "text-", "bg-", "flex", "grid", "w-", "h-", "space-", "items-", "justify-"
                    ))
                }
            if not classes and not any(marker in lower for marker in ("tailwind", "@apply", "theme(")):
                continue

            def evidence(members: set[str], limit: int = 8) -> tuple[str, ...]:
                return tuple(sorted(members))[:limit]

            def observe(tailwind_id: str, members: set[str], confidence: float = 0.9) -> None:
                if members:
                    observations.append(TailwindObservation(tailwind_id, evidence(members), confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, members: set[str]) -> None:
                if members:
                    findings.append(TailwindFinding(issue, category, severity, explanation, correction, evidence(members)))

            variants = {token.split(":")[0] for token in classes if ":" in token}
            bases = {token.split(":")[-1] for token in classes}
            responsive = {token for token in classes if token.split(":")[0] in {"sm", "md", "lg", "xl", "2xl"}}
            states = {token for token in classes if any(f"{state}:" in token for state in ("hover", "focus", "active", "disabled", "group-hover", "focus-visible"))}
            dark = {token for token in classes if token.startswith("dark:")}
            arbitrary = {token for token in classes if "[" in token and "]" in token}
            observe("utility_classes", classes, 0.98)
            observe("responsive_variants", responsive)
            observe("states", states)
            observe("dark_mode", dark)
            observe("arbitrary_values", arbitrary)
            observe("spacing", {token for token in bases if re.match(r"(?:p|m|space|gap)(?:[trblxyse])?-", token)})
            observe("typography", {token for token in bases if token.startswith(("text-", "font-", "leading-", "tracking-"))})
            observe("flex", {token for token in bases if token == "flex" or token.startswith(("flex-", "items-", "justify-", "content-", "self-"))})
            observe("grid", {token for token in bases if token == "grid" or token.startswith(("grid-", "col-", "row-"))})
            observe("positioning", {token for token in bases if token in {"static", "fixed", "absolute", "relative", "sticky"} or token.startswith(("inset-", "top-", "right-", "bottom-", "left-", "z-"))})
            observe("colors", {token for token in bases if token.startswith(("text-", "bg-", "border-", "from-", "via-", "to-", "ring-"))})
            observe("sizing", {token for token in bases if token.startswith(("w-", "min-w-", "max-w-", "h-", "min-h-", "max-h-"))})
            if "tailwind.config" in path.casefold() or "theme(" in lower:
                observe("configuration", {path}, 0.95)
            if "plugin(" in lower or "plugins:" in lower or "addutilities" in lower or "matchutilities" in lower:
                observe("plugins", {path}, 0.95)
            if "components" in path.casefold() or "@apply" in lower or re.search(r"\bbtn-\w+", lower):
                observe("component_patterns", {path, "@apply/component-like class usage"}, 0.82)

            def group(prefixes: tuple[str, ...]) -> dict[str, set[str]]:
                result: dict[str, set[str]] = {}
                for token in classes:
                    base = token.split(":")[-1]
                    prefix = next((item for item in prefixes if base.startswith(item)), None)
                    if prefix:
                        variant = token[:-(len(base) + 1)] if ":" in token else ""
                        result.setdefault(variant, set()).add(token)
                return result

            for label, prefixes in (
                ("spacing", ("p-", "px-", "py-", "pt-", "pr-", "pb-", "pl-")),
                ("margin", ("m-", "mx-", "my-", "mt-", "mr-", "mb-", "ml-")),
                ("sizing", ("w-", "min-w-", "max-w-", "h-", "min-h-", "max-h-")),
                ("text_color", ("text-",)),
                ("background_color", ("bg-",)),
            ):
                for variant, members in group(prefixes).items():
                    if len(members) > 1:
                        finding("conflicting_utilities", label, "medium",
                                "Multiple utilities in the same variant family may override one another; final output depends on generated CSS order.",
                                "Keep one intentional utility per property or verify the generated order and use a component abstraction.", members)
            duplicates = {token for token in class_tokens if class_tokens.count(token) > 1}
            finding("redundant_utility_class", "maintainability", "low",
                    "The same utility appears more than once in a class attribute.", "Remove duplicate tokens.", duplicates)
            responsive_bases = {token.split(":")[-1].split("-", 1)[0] for token in responsive}
            if responsive and not any(base in {item.split("-", 1)[0] for item in bases} for base in responsive_bases):
                finding("responsive_variant_without_base", "responsive_design", "low",
                        "A responsive utility is present without a visible base utility for the same property.",
                        "Confirm the mobile-first default is intentional and define a base value when needed.", responsive)
        return TailwindAnalysis(tuple(observations), tuple(findings))

    def explain_bootstrap(self, bootstrap_id: str) -> dict[str, Any]:
        profile = self.bootstrap_profiles().get(bootstrap_id)
        if profile is None:
            raise KeyError(f"unknown Bootstrap concept: {bootstrap_id}")
        return {"id": bootstrap_id, **profile}

    def analyze_bootstrap(self, source: Mapping[str, str] | str) -> BootstrapAnalysis:
        files = self._project_files(source)
        observations: list[BootstrapObservation] = []
        findings: list[BootstrapFinding] = []
        bootstrap_tokens = re.compile(
            r"^(?:container(?:-(?:fluid|sm|md|lg|xl|xxl))?|row|col(?:-(?:sm|md|lg|xl|xxl))?(?:-\d+)?|"
            r"g-\d+|d-(?:none|inline|block|flex|grid)|flex(?:-\w+)?|justify-content-\w+|align-items-\w+|"
            r"m[trblxy]?-\d+|p[trblxy]?-\d+|(?:text|bg|border)-(?:primary|secondary|success|danger|warning|info|light|dark|white|black)|"
            r"btn(?:-(?:primary|secondary|success|danger|warning|info|light|dark|link|outline-\w+))?|"
            r"navbar(?:-\w+)?|nav(?:-\w+)?|card(?:-\w+)?|modal(?:-\w+)?|form-\w+|table(?:-\w+)?|"
            r"alert(?:-\w+)?|badge(?:-\w+)?|accordion(?:-\w+)?|carousel(?:-\w+)?|"
            r"fs-\d+|fw-\w+|w-\d+|h-\d+|position-\w+|top-\d+|start-\d+|translate-middle(?:-x|-y)?)$"
        )
        for path, code in files.items():
            lower = code.casefold()
            class_values = re.findall(r"""(?:class|className)\s*=\s*["']([^"']+)["']""", code)
            class_tokens = [token for value in class_values for token in re.split(r"\s+", value.strip()) if token]
            classes = set(class_tokens)
            bootstrap_classes = {token for token in classes if bootstrap_tokens.match(token)}
            is_bootstrap = bool(bootstrap_classes) or any(marker in lower for marker in (
                "bootstrap.min.css", "bootstrap.bundle", "getbootstrap.com", "bootstrap.scss", "$grid-breakpoints",
            ))
            if not is_bootstrap:
                continue

            def evidence(members: set[str], limit: int = 8) -> tuple[str, ...]:
                return tuple(sorted(members))[:limit]

            def observe(bootstrap_id: str, members: set[str], confidence: float = 0.9) -> None:
                if members:
                    observations.append(BootstrapObservation(bootstrap_id, evidence(members), confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, members: set[str]) -> None:
                if members:
                    findings.append(BootstrapFinding(issue, category, severity, explanation, correction, evidence(members)))

            observe("bootstrap_detection", bootstrap_classes or {path}, 0.98)
            observe("grid", {token for token in bootstrap_classes if token == "row" or token.startswith(("col", "g-"))})
            observe("containers", {token for token in bootstrap_classes if token.startswith("container")})
            observe("breakpoints", {token for token in bootstrap_classes if re.search(r"(?:col|container)-(sm|md|lg|xl|xxl)", token)})
            observe("utilities", {token for token in bootstrap_classes if token.startswith(("d-", "m", "p", "text-", "bg-", "border-", "w-", "h-", "position-", "top-", "start-", "translate-"))})
            observe("components", {token for token in bootstrap_classes if token.startswith(("alert", "badge", "accordion", "carousel", "table", "card", "modal", "navbar", "btn", "form-"))})
            observe("forms", {token for token in bootstrap_classes if token.startswith("form-")})
            observe("buttons", {token for token in bootstrap_classes if token.startswith("btn")})
            observe("modals", {token for token in bootstrap_classes if token.startswith("modal")})
            observe("navbar", {token for token in bootstrap_classes if token.startswith(("navbar", "nav-"))})
            observe("cards", {token for token in bootstrap_classes if token.startswith("card")})
            responsive = {token for token in bootstrap_classes if re.search(r"(?:^|-)sm-|(?:^|-)md-|(?:^|-)lg-|(?:^|-)xl-|(?:^|-)xxl-", token)}
            observe("responsive_utilities", responsive)
            if "bootstrap.scss" in lower or "$grid-breakpoints" in lower or "$theme-colors" in lower:
                observe("customization", {path}, 0.95)
            if "data-bs-toggle" in lower or "data-toggle" in lower:
                observe("javascript_components", {"data-bs-toggle/data-toggle"}, 0.95)

            grid_rows = {token for token in bootstrap_classes if token == "row"}
            columns = {token for token in bootstrap_classes if token.startswith("col")}
            if columns and not grid_rows:
                finding("columns_without_row", "grid", "low",
                        "Bootstrap column classes are present without a visible row wrapper.",
                        "Place columns inside a .row or document the custom layout boundary.", columns)
            if responsive and not any(token in bootstrap_classes for token in {"col", "d-block", "d-flex", "d-none"}):
                finding("responsive_classes_without_base", "responsive_design", "low",
                        "Responsive Bootstrap classes are present without a clear base utility for the same region.",
                        "Confirm the mobile-first default behavior explicitly.", responsive)
            duplicate_classes = {token for token in class_tokens if class_tokens.count(token) > 1}
            finding("redundant_bootstrap_class", "maintainability", "low",
                    "The same Bootstrap class appears more than once in a class attribute.",
                    "Remove duplicate class tokens.", duplicate_classes)
            if "modal" in bootstrap_classes and "data-bs-toggle" not in lower and "data-toggle" not in lower:
                finding("modal_trigger_contract_missing", "components", "medium",
                        "A modal class is present but no Bootstrap data trigger is visible in the supplied markup.",
                        "Verify that JavaScript initialization or an explicit trigger is wired.", {"modal"})
        return BootstrapAnalysis(tuple(observations), tuple(findings))

    def explain_react(self, react_id: str) -> dict[str, Any]:
        profile = self.react_profiles().get(react_id)
        if profile is None:
            raise KeyError(f"unknown React concept: {react_id}")
        return {"id": react_id, **profile}

    def analyze_react(self, source: Mapping[str, str] | str) -> ReactAnalysis:
        files = self._project_files(source)
        observations: list[ReactObservation] = []
        findings: list[ReactFinding] = []
        for path, code in files.items():
            lower = code.casefold()
            if not (path.casefold().endswith((".jsx", ".tsx")) or
                    re.search(r"\b(?:from\s+['\"]react['\"]|import\s+React|useState\s*\(|return\s*\(?\s*<)", code)):
                continue
            lines = code.splitlines()

            def evidence(pattern: str, limit: int = 4) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(lines, 1)
                    if matcher.search(line)
                )[:limit]

            def observe(react_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(ReactObservation(react_id, found, confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(ReactFinding(issue, category, severity, explanation, correction, found))

            observe("components", r"\b(?:function|const|class)\s+[A-Z]\w*|React\.Component", 0.98)
            observe("jsx", r"<[A-Z][\w.]*[\s/>]|<[a-z][\w-]*(?:\s|>)", 0.98)
            observe("rendering", r"\bcreateRoot\s*\(|\bReactDOM\.render\s*\(|\brender\s*\(|return\s*\(?\s*<", 0.9)
            observe("props", r"\bprops\b|function\s+[A-Z]\w*\s*\(\s*\{|=>\s*\(\s*\{", 0.88)
            observe("state", r"\buseState\s*\(|\buseReducer\s*\(|\bthis\.state\b", 0.98)
            observe("hooks", r"\buse[A-Z]\w*\s*\(", 0.95)
            observe("useState", r"\buseState\s*\(", 0.98)
            observe("useEffect", r"\buseEffect\s*\(", 0.98)
            observe("useMemo", r"\buseMemo\s*\(", 0.98)
            observe("useCallback", r"\buseCallback\s*\(", 0.98)
            observe("useRef", r"\buseRef\s*\(", 0.98)
            observe("context", r"\b(?:createContext|useContext|\.Provider|\.Consumer)\b", 0.95)
            observe("reducers", r"\buseReducer\s*\(|\breducer\b|\bdispatch\s*\(", 0.9)
            observe("forms", r"<form\b|onSubmit\s*=|value\s*=|onChange\s*=", 0.92)
            observe("events", r"\bon[A-Z]\w*\s*=|addEventListener\s*\(", 0.92)
            observe("reconciliation", r"\bkey\s*=", 0.85)
            observe("component_lifecycle", r"\buseEffect\s*\(|componentDidMount|componentWillUnmount|componentDidUpdate", 0.92)
            observe("routing", r"\b(?:BrowserRouter|HashRouter|Routes?|Route|useNavigate|useParams|Link)\b", 0.96)
            observe("state_management", r"\b(?:Redux|configureStore|createSlice|Provider|zustand|jotai|recoil)\b", 0.9)

            if re.search(r"\buseeffect\s*\(", lower) and re.search(r"\bfetch\s*\(", lower) and not re.search(r"\breturn\s*\(\s*\(\s*\)\s*=>|abortcontroller|cancel", lower):
                finding("effect_request_without_cleanup", "effects", "medium",
                        "An effect performs a request without visible cancellation or cleanup evidence.",
                        "Use AbortController or a request library lifecycle mechanism when the component can unmount.", r"\buseEffect\s*\(")
            if re.search(r"\buseeffect\s*\(\s*\([^)]*\)\s*=>\s*\{", lower) and re.search(r"\[\s*\]\s*\)", lower) and re.search(r"\bprops\.|\bstate\b|\bfetch\s*\(", lower):
                finding("effect_dependency_risk", "effects", "medium",
                        "An effect with an empty dependency array appears to read props or state.",
                        "Declare the values the effect uses, or document a stable initial-only contract.", r"\buseEffect\s*\(")
            if re.search(r"\bsetstate\s*\(\s*[^)]*\)", lower) and re.search(r"\buseeffect\s*\(", lower) and not re.search(r"\[\s*\w+\s*\]", lower):
                finding("possible_effect_render_loop", "rendering", "medium",
                        "State is updated from an effect without enough dependency evidence to rule out repeated renders.",
                        "Check dependencies and guard updates so the effect converges.", r"\bsetState\s*\(|\bset[A-Z]\w*\s*\(")
            if re.search(r"\.map\s*\([^)]*=>\s*<", lower) and not re.search(r"\bkey\s*=", lower):
                finding("mapped_elements_without_key", "reconciliation", "medium",
                        "A mapped JSX collection has no visible key prop for reconciliation.",
                        "Provide a stable key from the item identity, not the array index when order can change.", r"\.map\s*\(")
            if re.search(r"\busememo\s*\(", lower) and not re.search(r"\breturn\b", lower):
                finding("usememo_without_visible_value", "design", "low",
                        "useMemo is present but the supplied code does not show a memoized returned value.",
                        "Keep memoization only when it protects an evidenced expensive calculation.", r"\buseMemo\s*\(")
            if re.search(r"\busecallback\s*\(", lower) and not re.search(r"\buseeffect\b|\bmemo\s*\(", lower):
                finding("unjustified_usecallback", "design", "low",
                        "useCallback is present without visible evidence of a memoized consumer or dependency-sensitive effect.",
                        "Use it only when referential stability provides a measured benefit.", r"\buseCallback\s*\(")
            if re.search(r"<form\b", lower) and re.search(r"<input\b", lower) and not re.search(r"\bname\s*=", lower):
                finding("form_control_without_name", "forms", "low",
                        "A form input has no visible name attribute for native submission or form semantics.",
                        "Add a stable name or use an explicitly controlled form contract.", r"<input\b")
            if re.search(r"\busecontext\s*\(", lower) and not re.search(r"\bprovider\b", lower):
                finding("context_provider_not_visible", "state_management", "low",
                        "Context is consumed but no provider is visible in the supplied files.",
                        "Verify the provider boundary exists higher in the application tree.", r"\buseContext\s*\(")
        return ReactAnalysis(tuple(observations), tuple(findings))

    def explain_vue(self, vue_id: str) -> dict[str, Any]:
        profile = self.vue_profiles().get(vue_id)
        if profile is None:
            raise KeyError(f"unknown Vue concept: {vue_id}")
        return {"id": vue_id, **profile}

    def analyze_vue(self, source: Mapping[str, str] | str) -> VueAnalysis:
        files = self._project_files(source)
        observations: list[VueObservation] = []
        findings: list[VueFinding] = []
        for path, code in files.items():
            lower = code.casefold()
            if not (path.casefold().endswith(".vue") or re.search(
                r"\b(?:from\s+['\"]vue['\"]|createApp\s*\(|defineComponent\s*\(|Vue\.extend|Pinia|vue-router)\b", code
            )):
                continue
            lines = code.splitlines()

            def evidence(pattern: str, limit: int = 4) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(lines, 1)
                    if matcher.search(line)
                )[:limit]

            def observe(vue_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(VueObservation(vue_id, found, confidence))

            def finding(issue: str, category: str, severity: str, explanation: str,
                        correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(VueFinding(issue, category, severity, explanation, correction, found))

            vue3 = re.search(r"\b(?:createApp|script\s+setup|defineComponent|ref\s*\(|reactive\s*\(|computed\s*\()", code)
            vue2 = re.search(r"\b(?:Vue\.extend|new\s+Vue|beforeCreate|created|beforeDestroy|filters\s*:|data\s*\(\s*\)|mounted\s*\(\s*\))", code)
            observe("components", r"<template\b|export\s+default|defineComponent\s*\(|Vue\.extend", 0.98)
            observe("props", r"\bprops\s*:|defineProps\s*\(|this\.\$props|props\.", 0.95)
            observe("emits", r"\bemits\s*:|defineEmits\s*\(|\$emit\s*\(|emit\s*\(", 0.95)
            observe("reactive_state", r"\b(?:ref|reactive|shallowRef|readonly)\s*\(|\bdata\s*\(\s*\)", 0.98)
            observe("computed", r"\bcomputed\s*\(|\bcomputed\s*:", 0.98)
            observe("watchers", r"\bwatch\s*\(|\bwatchEffect\s*\(|\bwatch\s*:", 0.98)
            observe("lifecycle", r"\bonMounted|onUpdated|onUnmounted|mounted\s*(?::|\()|updated\s*:|beforeDestroy|destroyed", 0.95)
            observe("directives", r"\bv-[\w-]+\b|directives\s*:|app\.directive", 0.92)
            observe("slots", r"<slot\b|\$slots|useSlots", 0.95)
            observe("composables", r"\buse[A-Z]\w*\s*(?:\(|,|})", 0.9)
            observe("vue_router", r"\b(?:vue-router|RouterView|RouterLink|useRoute|useRouter|router-view|router-link)\b", 0.98)
            observe("pinia", r"\b(?:pinia|defineStore|createPinia|use[A-Z]\w*Store)\b", 0.98)
            observe("composition_api", r"\b(?:setup\s*\(|script\s+setup|ref\s*\(|reactive\s*\(|computed\s*\(|onMounted\s*\()", 0.98)
            observe("options_api", r"\b(?:data|computed|methods|watch|mounted|created|components)\s*:", 0.98)
            if vue3:
                observe("vue_version_3", r"\bcreateApp\b|\bscript\s+setup\b|\bdefineComponent\b|\bref\s*\(", 0.96)
            if vue2:
                observe("vue_version_2", r"\bVue\.extend\b|\bnew\s+Vue\b|\bbeforeDestroy\b|\bfilters\s*:", 0.9)

            if vue2 and vue3:
                finding("mixed_vue_api_versions", "architecture", "medium",
                        "The same supplied source contains evidence associated with Vue 2 and Vue 3 APIs.",
                        "Confirm the migration boundary and avoid mixing incompatible component contracts unintentionally.", r"(Vue\.extend|createApp|script\s+setup)")
            if re.search(r"\bwatch\s*\(|\bwatch\s*:", lower) and not re.search(r"\b(?:deep|immediate|onCleanup|cleanup|stop)\b", lower):
                finding("watcher_without_cleanup_or_options", "reactivity", "low",
                        "A watcher is present without visible cleanup or explicit options in the supplied code.",
                        "Stop or clean up external work inside the watcher when needed, and document deep/immediate behavior.", r"\bwatch(?:Effect)?\s*\(|\bwatch\s*:")
            if re.search(r"\b(?:onMounted|mounted\s*(?::|\())", lower) and not re.search(r"\b(?:onUnmounted|beforeUnmount|beforeDestroy|destroyed)\b", lower):
                finding("lifecycle_setup_without_teardown", "lifecycle", "medium",
                        "A mount lifecycle hook is present without a visible teardown hook.",
                        "Release listeners, timers, subscriptions and requests when the component is disposed.", r"\b(?:onMounted|mounted\s*(?::|\())")
            if re.search(r"\bdefineProps\s*\(|\bprops\s*:", lower) and re.search(r"\bthis\.\$emit\b|\$emit\s*\(", lower) and not re.search(r"\bemits\s*:|defineemits\b", lower):
                finding("event_emission_not_declared", "components", "low",
                        "The component emits an event without visible declaration of its event contract.",
                        "Declare emits explicitly so consumers and tooling can verify the interface.", r"\$emit\s*\(")
            if re.search(r"\bcomputed\s*:", lower) and re.search(r"\bcomputed\s*\(", lower):
                finding("mixed_computed_styles", "architecture", "low",
                        "Options API and Composition API computed declarations appear in the same source.",
                        "Use a deliberate migration boundary and keep the component's style consistent.", r"\bcomputed\b")
        return VueAnalysis(tuple(observations), tuple(findings))

    def explain_quasar(self, quasar_id: str) -> dict[str, Any]:
        profile = self.quasar_profiles().get(quasar_id)
        if profile is None:
            raise KeyError(f"unknown Quasar concept: {quasar_id}")
        return {"id": quasar_id, **profile}

    def analyze_quasar(self, source: Mapping[str, str] | str) -> QuasarAnalysis:
        files = self._project_files(source)
        observations: list[QuasarObservation] = []
        findings: list[QuasarFinding] = []
        for path, code in files.items():
            lower = code.casefold()
            quasar_components = set(re.findall(r"\bQ[A-Z][A-Za-z0-9]*\b|\bq-[a-z][\w-]*\b", code, re.IGNORECASE))
            is_quasar = "quasar.config" in path.casefold() or bool(quasar_components) or any(marker in lower for marker in (
                "quasar.config", "quasarframework", "@quasar/app", "quasar cli", "boot/", "q-table", "q-layout",
            ))
            if not is_quasar:
                continue

            def evidence(pattern: str, limit: int = 6) -> tuple[str, ...]:
                matcher = re.compile(pattern, re.IGNORECASE)
                return tuple(
                    f"{path}:{index}: {line.strip()}"
                    for index, line in enumerate(code.splitlines(), 1)
                    if matcher.search(line)
                )[:limit]

            def observe(quasar_id: str, pattern: str, confidence: float = 0.9) -> None:
                found = evidence(pattern)
                if found:
                    observations.append(QuasarObservation(quasar_id, found, confidence))

            def observe_context(quasar_id: str, context: str, confidence: float = 0.9) -> None:
                observations.append(QuasarObservation(quasar_id, (context,), confidence))

            def finding(issue: str, category: str, severity: str, explanation: str, correction: str, pattern: str) -> None:
                found = evidence(pattern)
                if found:
                    findings.append(QuasarFinding(issue, category, severity, explanation, correction, found))

            if "quasar.config" in path.casefold():
                observe_context("quasar_cli", f"{path}: Quasar CLI configuration file", 0.98)
            else:
                observe("quasar_cli", r"quasar\.config|@quasar/app|quasar\s+(?:dev|build|serve|clean)", 0.98)
            observe("components", r"\bQ[A-Z][A-Za-z0-9]*\b|\bq-[a-z][\w-]*\b", 0.98)
            observe("layouts", r"\bQLayout\b|\bQHeader\b|\bQDrawer\b|\bQPageContainer\b|\bQFooter\b|\bq-layout\b|\bq-page-container\b", 0.98)
            observe("pages", r"\bQPage\b|\bq-page\b|pages/|<router-view", 0.9)
            observe("boot_files", r"boot/|defineBoot|boot\s*:", 0.98)
            observe("plugins", r"src/plugins/|boot.*plugin|app\.use\s*\(|plugins\s*:", 0.88)
            observe("directives", r"\bv-close-popup\b|\bv-ripple\b|\bv-touch-(?:swipe|hold)|app\.directive", 0.95)
            observe("qtable", r"\bQTable\b|\bq-table\b|rows\s*=|columns\s*=", 0.98)
            observe("qform", r"\bQForm\b|\bq-form\b|@submit\.prevent|rules\s*=", 0.98)
            observe("dialogs", r"\b(?:QDialog|q-dialog|Dialog\.create|this\.\$q\.dialog)\b", 0.98)
            observe("notifications", r"\b(?:Notify\.create|this\.\$q\.notify|QNotify)\b", 0.98)
            observe("routing", r"\b(?:vue-router|RouterView|RouterLink|useRouter|useRoute|router-view)\b", 0.94)
            observe("pinia", r"\b(?:pinia|defineStore|createPinia|use[A-Z]\w*Store)\b", 0.94)
            observe("spa", r"mode\s*:\s*['\"]spa['\"]|spa\s*:", 0.98)
            observe("pwa", r"mode\s*:\s*['\"]pwa['\"]|pwa\s*:", 0.98)
            observe("capacitor", r"mode\s*:\s*['\"]capacitor['\"]|capacitor\s*:", 0.98)
            observe("electron", r"mode\s*:\s*['\"]electron['\"]|electron\s*:", 0.98)
            if "quasar.config" in path.casefold() and re.search(r"\bbuild\s*:", lower):
                observe_context("spa", f"{path}: default Quasar SPA build configuration", 0.8)
            if "quasar.config" in path.casefold() or "framework:" in lower or "build:" in lower:
                if "quasar.config" in path.casefold():
                    observations.append(QuasarObservation("configuration", (path,), 0.95))
                else:
                    observe("configuration", r"quasar\.config|framework\s*:|build\s*:", 0.95)
            if re.search(r"\bqtable\b|\bq-table\b", lower) and not re.search(r"\b(?:columns|row-key|pagination|loading)\s*=", lower):
                finding("qtable_contract_incomplete", "components", "medium",
                        "QTable is present without visible columns, row identity, pagination or loading configuration.",
                        "Verify the QTable data contract and configure the options required by the table behavior.", r"\bQTable\b|\bq-table\b")
            if re.search(r"\bqform\b|\bq-form\b", lower) and not re.search(r"\brules\s*=", lower) and not re.search(r"@submit", lower):
                finding("qform_without_validation_or_submit_contract", "forms", "low",
                        "QForm is present without visible validation rules or submit handling.",
                        "Define field rules and an explicit submit boundary when the form requires validation.", r"\bQForm\b|\bq-form\b")
            if re.search(r"\bqdialog\b|\bq-dialog\b|\bdialog\.create", lower) and not re.search(r"\bhide\b|\bv-close-popup\b|@hide", lower):
                finding("dialog_dismissal_not_visible", "dialogs", "low",
                        "A dialog is used without visible dismissal or close handling.",
                        "Provide an accessible close path and handle dialog lifecycle explicitly.", r"\bQDialog\b|\bq-dialog\b|\bDialog\.create")
            if "pwa" in lower and not re.search(r"service[-_]?worker|registerSW|workbox", lower):
                finding("pwa_without_service_worker_evidence", "pwa", "medium",
                        "PWA mode is declared but no service worker registration evidence is supplied.",
                        "Verify Quasar PWA configuration and service worker registration/build output.", r"\bpwa\b")
            if "capacitor" in lower and not re.search(r"@capacitor|cordova|plugins", lower):
                finding("capacitor_mode_without_native_contract", "capacitor", "low",
                        "Capacitor mode is declared without visible native plugin or platform configuration.",
                        "Verify the Capacitor project and native plugin contract separately.", r"\bcapacitor\b")
        return QuasarAnalysis(tuple(observations), tuple(findings))

    def analyze_frontend_architecture(
        self, source: Mapping[str, str] | str
    ) -> FrontendArchitectureAnalysis:
        files = self._project_files(source)
        observations: list[FrontendArchitectureObservation] = []
        findings: list[FrontendArchitectureFinding] = []
        entries = [(path, code, code.casefold()) for path, code in files.items()]
        combined = "\n".join(f"{path}\n{code}" for path, code, _ in entries).casefold()

        def evidence(pattern: str, limit: int = 8) -> tuple[str, ...]:
            matcher = re.compile(pattern, re.IGNORECASE)
            result = []
            for path, code, _ in entries:
                for index, line in enumerate(code.splitlines(), 1):
                    if matcher.search(line):
                        result.append(f"{path}:{index}: {line.strip()}")
            return tuple(result[:limit])

        def observe(architecture_id: str, pattern: str, confidence: float = 0.9) -> None:
            found = evidence(pattern)
            if found:
                observations.append(FrontendArchitectureObservation(architecture_id, found, confidence))

        def observe_paths(architecture_id: str, pattern: str, confidence: float = 0.88) -> None:
            matcher = re.compile(pattern, re.IGNORECASE)
            found = tuple(f"{path}: path indicates {architecture_id}" for path, _, _ in entries if matcher.search(path))
            if found:
                observations.append(FrontendArchitectureObservation(architecture_id, found, confidence))

        def finding(
            issue: str,
            category: str,
            severity: str,
            explanation: str,
            correction: str,
            pattern: str,
        ) -> None:
            found = evidence(pattern)
            if found:
                findings.append(FrontendArchitectureFinding(
                    issue, category, severity, explanation, correction, found
                ))

        observe_paths("component_architecture", r"(?:components?|layouts?|pages?|views?)[\\/]")
        observe_paths("api_layers", r"(?:api|services?|repositories?)[\\/]")
        observe_paths("routing", r"(?:routes?|router)[\\/]")
        observe_paths("forms", r"forms?[\\/]")
        observe_paths("design_systems", r"(?:tokens?|theme|design[-_ ]system|styles?)[\\/]")
        observe("component_architecture", r"\b(?:component|defineComponent|function\s+[A-Z]\w*|class\s+\w+Component)\b|<[A-Z][\w.]*\b")
        observe("state_management", r"\b(?:useState|useReducer|useStore|createStore|defineStore|redux|zustand|pinia|recoil|mobx|reactive|ref)\b")
        observe("routing", r"\b(?:react-router|vue-router|RouterProvider|Routes?|Route\b|createRouter|router-view|navigate\s*\(|useNavigate)\b")
        observe("api_layers", r"\b(?:axios|fetch\s*\(|graphql|urql|apollo|apiClient|api/|services?/|repositories?/)\b")
        observe("composables_hooks", r"\b(?:use[A-Z]\w*|composables?/|hooks?/|computed|watch(?:Effect)?|onMounted)\b")
        observe("reusable_components", r"\b(?:Button|Input|Modal|Table|Card|Form|use[A-Z]\w*)\b|components?/")
        observe("design_systems", r"\b(?:theme|tokens?|design[-_ ]system|storybook|tailwind|bootstrap|mui|chakra|quasar)\b|variables?\s*[:=]")
        observe("forms", r"(?:<form\b|useForm|Formik|react-hook-form|vee-validate|QForm|v-model|onSubmit|handleSubmit)")
        observe("validation", r"\b(?:yup|zod|joi|validator|validation|rules\s*[=:]|required\s*[:=]|schema\s*[=:])\b|z\.object\s*\(")
        observe("authentication", r"\b(?:login|logout|signIn|signOut|access[_-]?token|refresh[_-]?token|authentication|auth\b)\b")
        observe("authorization", r"\b(?:can[A-Z]\w*|hasPermission|hasRole|roles?\b|permissions?\b|protectedRoute|guard\b|authorize)\b")
        observe("error_handling", r"\b(?:try\s*\{|catch\s*\(|ErrorBoundary|onError|errorHandler|setError|error\s*state|isError)\b")
        observe("loading_states", r"\b(?:loading|isLoading|pending|skeleton|spinner|Suspense|fallback)\b")
        observe("caching", r"\b(?:cache|cached|staleTime|queryClient|react-query|tanstack|swr|localStorage|sessionStorage)\b")
        observe("responsive_architecture", r"\b(?:responsive|breakpoints?|media\s*query|useMediaQuery|sm:|md:|lg:|grid|flex)\b")

        if re.search(r"\b(?:fetch\s*\(|axios\.(?:get|post|put|delete)|apiClient\.)", combined) and not re.search(
            r"\b(?:try\s*\{|catch\s*\(|ErrorBoundary|onError|error\s*[:=]|isError|loading|isLoading|pending)\b",
            combined,
        ):
            finding(
                "api_layer_without_error_or_loading_boundary", "api_layers", "medium",
                "API request evidence exists without a visible error or loading state in the supplied frontend sources.",
                "Expose explicit pending and error states at the API boundary or through a shared data-fetching layer.",
                r"\b(?:fetch\s*\(|axios\.(?:get|post|put|delete)|apiClient\.)",
            )
        if re.search(r"<form\b|useForm|Formik|react-hook-form|QForm|v-model", combined) and not re.search(
            r"validation|rules\s*[=:]|schema\s*[=:]|yup|zod|joi|required\s*[:=]", combined
        ):
            finding(
                "form_without_visible_validation", "forms", "medium",
                "A form boundary is visible but no validation rule or schema is visible in the supplied sources.",
                "Define a validation contract near the form and surface field-level errors before submission.",
                r"<form\b|useForm|Formik|react-hook-form|QForm|v-model",
            )
        if re.search(r"\b(?:login|signIn|access[_-]?token|authentication|auth\b)", combined) and not re.search(
            r"\b(?:can[A-Z]\w*|hasPermission|hasRole|roles?\b|permissions?\b|protectedRoute|guard\b|authorize)",
            combined,
        ):
            finding(
                "authentication_without_authorization_boundary", "security", "medium",
                "Authentication evidence exists without a visible route guard, role or permission boundary.",
                "Separate identity verification from authorization and protect privileged routes or actions explicitly.",
                r"\b(?:login|signIn|access[_-]?token|authentication|auth\b)",
            )
        if re.search(r"\b(?:useState|useReducer|reactive|ref|defineStore|createStore)\b", combined) and not re.search(
            r"\b(?:Provider|createStore|defineStore|useStore|createPinia|store)\b", combined
        ):
            finding(
                "shared_state_without_visible_boundary", "state_management", "low",
                "State management evidence is present, but no visible provider/store boundary was found.",
                "Keep shared state behind an explicit provider, store module or domain boundary.",
                r"\b(?:useState|useReducer|reactive|ref|defineStore|createStore)\b",
            )
        return FrontendArchitectureAnalysis(tuple(observations), tuple(findings))

    def analyze_git(self, repository: Mapping[str, Any] | str) -> GitAnalysis:
        """Analyze supplied Git metadata without running Git commands."""
        data = {"history": repository} if isinstance(repository, str) else dict(repository)
        observations: list[GitObservation] = []
        findings: list[GitFinding] = []

        def observe(git_id: str, evidence: tuple[str, ...], confidence: float = 0.8) -> None:
            observations.append(GitObservation(git_id, evidence, confidence))

        def finding(issue: str, category: str, severity: str, explanation: str,
                    correction: str, evidence: tuple[str, ...],
                    related: tuple[dict[str, Any], ...] = ()) -> None:
            findings.append(GitFinding(issue, category, severity, explanation, correction, evidence, related))

        commits = list(data.get("commits", []))
        history = data.get("history", "")
        if isinstance(history, list):
            commits.extend(history)
        for key, git_id, label in (
            ("branches", "branches", "branch records"),
            ("tags", "tags", "tag records"),
            ("diffs", "diffs", "diff records"),
            ("conflicts", "conflicts", "conflict records"),
            ("pull_requests", "pull_requests", "pull request records"),
        ):
            values = data.get(key, data.get("pullRequests", []) if key == "pull_requests" else [])
            if values:
                observe(git_id, (f"{len(values)} {label} supplied",), 0.95)
        if commits:
            observe("commits", (f"{len(commits)} commit records supplied",), 0.95)
            observe("history", ("commit history supplied",), 0.9)
        conflicts = data.get("conflicts", [])
        if conflicts:
            finding(
                "unresolved_conflicts", "integrity", "high",
                "The supplied repository metadata contains merge conflicts.",
                "Resolve conflicts, run relevant tests, and inspect the final diff before committing.",
                (f"{len(conflicts)} conflict records",),
            )
        operation_text = " ".join(
            str(item.get("message", item.get("title", "")))
            for item in commits if isinstance(item, dict)
        ).casefold()
        operations = {
            "merge": ("merge", "A merge combines histories and may introduce conflicts.", "Review both parent histories and the resulting diff."),
            "rebase": ("rebase", "A rebase rewrites commit ancestry and changes commit identities.", "Coordinate before rewriting shared history."),
            "cherry-pick": ("cherry_pick", "A cherry-pick copies a change onto another line of history.", "Verify dependencies and duplicate changes."),
            "stash": ("stash", "A stash temporarily hides uncommitted work.", "Track stashes and keep another copy of important work."),
            "revert": ("revert", "A revert adds a commit that undoes an earlier change.", "Verify follow-up changes and affected tests."),
            "reset": ("reset", "A reset moves a branch reference and can discard local work.", "Require confirmation and preserve a recoverable reference."),
            "pull": ("pull", "A pull integrates remote changes into the local branch.", "Inspect fetched changes and test before publishing."),
            "push": ("push", "A push publishes local commits to a remote.", "Verify remote, branch, permissions, and review state."),
        }
        for operation, (git_id, _explanation, _correction) in operations.items():
            if operation in operation_text:
                observe(git_id, (f"{operation} mentioned in history metadata",), 0.7)
        records = [
            item for key in ("bugs", "tasks", "incidents")
            for item in data.get(key, []) if isinstance(item, dict)
        ]
        for commit in commits:
            if not isinstance(commit, dict):
                continue
            message = str(commit.get("message", ""))
            matches = tuple(
                record for record in records
                if any(
                    token and token.casefold() in message.casefold()
                    for token in (str(record.get("id", "")), str(record.get("title", record.get("titulo", ""))))
                )
            )
            if matches:
                finding(
                    "change_related_to_work_item", "traceability", "low",
                    "A commit message matches supplied bug, task, or incident metadata.",
                    "Keep the work-item reference in the commit or pull request and verify the final diff.",
                    (message,), matches,
                )
        if data.get("diffs") and not commits:
            finding(
                "diff_without_history_context", "traceability", "low",
                "A diff was supplied without its associated commit or branch history.",
                "Provide commit, branch, or pull request context before attributing the change.",
                ("diff records without commits",),
            )
        return GitAnalysis(tuple(observations), tuple(findings))

    @staticmethod
    def protocol_profiles() -> dict[str, dict[str, Any]]:
        return {
            "http": {"definition": "Request-response application protocol for web communication.", "uses": ["APIs", "web resources"], "risks": ["cleartext transport without TLS"]},
            "https": {"definition": "HTTP protected by TLS.", "uses": ["confidential web transport", "server authentication"], "risks": ["certificate and termination misconfiguration"]},
            "rest": {"definition": "Resource-oriented HTTP API style using standard methods and representations.", "uses": ["stateless APIs"], "risks": ["inconsistent resource and error semantics"]},
            "json": {"definition": "Text data interchange format based on objects, arrays, and primitive values.", "uses": ["API payloads"], "risks": ["schema drift and ambiguous types"]},
            "xml": {"definition": "Tagged, extensible data interchange format.", "uses": ["legacy and document APIs"], "risks": ["parser and entity-expansion hazards"]},
            "websockets": {"definition": "Persistent bidirectional connection over an HTTP upgrade.", "uses": ["live collaboration", "real-time updates"], "risks": ["connection lifecycle and backpressure"]},
            "sse": {"definition": "Server-to-client event stream over HTTP.", "uses": ["notifications", "progress streams"], "risks": ["reconnect and proxy buffering behavior"]},
            "graphql": {"definition": "Typed API query language and execution model.", "uses": ["client-shaped data queries"], "risks": ["query cost and authorization complexity"]},
            "oauth": {"definition": "Delegated authorization framework using grants and tokens.", "uses": ["third-party access"], "risks": ["redirect, scope, and token lifecycle errors"]},
            "jwt": {"definition": "Signed token format carrying claims.", "uses": ["stateless authentication"], "risks": ["key, expiry, audience, and revocation mistakes"]},
            "cookies": {"definition": "Browser-managed name-value state sent with matching requests.", "uses": ["sessions", "preferences"], "risks": ["scope, SameSite, Secure, and HttpOnly errors"]},
            "sessions": {"definition": "Server-side or session-backed state associated with a client.", "uses": ["web authentication"], "risks": ["fixation, expiry, and distributed storage issues"]},
            "headers": {"definition": "Metadata fields controlling HTTP requests and responses.", "uses": ["content negotiation", "auth", "caching"], "risks": ["missing or contradictory contract metadata"]},
            "status_codes": {"definition": "Standard numeric outcome classes for HTTP exchanges.", "uses": ["machine-readable result handling"], "risks": ["using success codes for failures"]},
            "cors": {"definition": "Browser policy and response headers for cross-origin requests.", "uses": ["separate frontend/backend origins"], "risks": ["overbroad origins and credential incompatibility"]},
            "csrf": {"definition": "Protection against cross-site state-changing requests.", "uses": ["cookie-authenticated browsers"], "risks": ["missing token or unsafe exemptions"]},
            "rate_limiting": {"definition": "Controls request frequency or quota per client or identity.", "uses": ["abuse prevention", "capacity protection"], "risks": ["false positives and retry storms"]},
        }

    @staticmethod
    def database_profiles() -> dict[str, dict[str, Any]]:
        return {
            "sql": {"definition": "Declarative language for querying and modifying relational data.", "uses": ["queries", "schema definition"], "risks": ["ambiguous contracts and unsafe dynamic SQL"]},
            "mysql": {"definition": "Relational database commonly used for transactional web workloads.", "uses": ["OLTP", "web applications"], "risks": ["engine, collation, and mode differences"]},
            "postgresql": {"definition": "Feature-rich relational database with strong SQL and extensibility.", "uses": ["OLTP", "analytics", "geospatial workloads"], "risks": ["operational and extension complexity"]},
            "sqlite": {"definition": "Embedded relational database stored in a local file.", "uses": ["local apps", "tests", "embedded systems"], "risks": ["write concurrency and dialect differences"]},
            "indexes": {"definition": "Auxiliary structures that accelerate selected access paths.", "uses": ["filters", "joins", "ordering"], "risks": ["write overhead and unused indexes"]},
            "primary_keys": {"definition": "Constraint identifying each row uniquely.", "uses": ["identity", "referential targets"], "risks": ["unstable or non-unique identifiers"]},
            "foreign_keys": {"definition": "Constraint preserving references between tables.", "uses": ["relational integrity"], "risks": ["orphan rows or delete-order surprises when absent"]},
            "constraints": {"definition": "Database-enforced rules for valid values and relationships.", "uses": ["invariants", "uniqueness", "validation"], "risks": ["rules only enforced in application code"]},
            "joins": {"definition": "Combines rows across relations using matching conditions.", "uses": ["relational projections"], "risks": ["cardinality explosions and incorrect predicates"]},
            "transactions": {"definition": "Atomic unit of database work with commit or rollback.", "uses": ["consistent multi-step writes"], "risks": ["long locks and partial writes without boundaries"]},
            "isolation": {"definition": "Controls visibility and interference among concurrent transactions.", "uses": ["concurrency correctness"], "risks": ["anomalies or excessive locking"]},
            "normalization": {"definition": "Organizes data to reduce update anomalies and redundancy.", "uses": ["write models"], "risks": ["read complexity when over-normalized"]},
            "denormalization": {"definition": "Duplicates or precomputes data to optimize reads.", "uses": ["read models", "reporting"], "risks": ["stale or inconsistent copies"]},
            "migrations": {"definition": "Versioned, repeatable changes to database schema.", "uses": ["deployment evolution"], "risks": ["irreversible or non-idempotent changes"]},
            "seeders": {"definition": "Code or data fixtures that populate known records.", "uses": ["development", "reference data"], "risks": ["non-repeatable or production-unsafe data"]},
            "orm": {"definition": "Mapping layer between application objects and relational persistence.", "uses": ["developer productivity", "unit-of-work mapping"], "risks": ["hidden queries and leaky abstractions"]},
            "n_plus_one": {"definition": "One initial query followed by one query per returned item.", "uses": ["none; it is an anti-pattern"], "risks": ["latency and database load growth with result size"]},
            "query_optimization": {"definition": "Improving query execution using plans, predicates, projections, and indexes.", "uses": ["measured performance work"], "risks": ["premature tuning and write cost"]},
        }

    @staticmethod
    def _project_files(project: Mapping[str, str] | str) -> dict[str, str]:
        if isinstance(project, str):
            return {"<source>.py": project}
        return {str(path): str(source) for path, source in project.items()}

    @staticmethod
    def architecture_profiles() -> dict[str, dict[str, Any]]:
        return {
            "monolith": {"definition": "One deployable application boundary containing most capabilities.", "strengths": ["simple deployment"], "tradeoffs": ["large change and scaling boundary"]},
            "modular_monolith": {"definition": "One deployable unit with explicit internal module boundaries.", "strengths": ["module isolation without distributed operations"], "tradeoffs": ["boundaries can erode inside one process"]},
            "microservices": {"definition": "Independently deployable services organized around capabilities.", "strengths": ["independent scaling and deployment"], "tradeoffs": ["network, data consistency, and operational complexity"]},
            "soa": {"definition": "Coarse-grained services integrated through enterprise contracts.", "strengths": ["integration across heterogeneous systems"], "tradeoffs": ["governance and contract overhead"]},
            "event_driven": {"definition": "Components communicate by publishing and consuming events.", "strengths": ["loose temporal coupling"], "tradeoffs": ["ordering, retries, and observability complexity"]},
            "layered": {"definition": "Responsibilities are separated into horizontal layers.", "strengths": ["familiar separation of concerns"], "tradeoffs": ["cross-layer coupling and pass-through code"]},
            "hexagonal": {"definition": "Domain logic is isolated behind ports with adapters at the boundary.", "strengths": ["testable domain and replaceable infrastructure"], "tradeoffs": ["more boundary types and wiring"]},
            "clean": {"definition": "Dependencies point inward toward stable business rules.", "strengths": ["business rules isolated from frameworks"], "tradeoffs": ["indirection and mapping overhead"]},
            "mvc": {"definition": "Model, view, and controller responsibilities are separated.", "strengths": ["clear web presentation roles"], "tradeoffs": ["controllers or models can become oversized"]},
            "cqrs": {"definition": "Command writes and query reads use separate models or paths.", "strengths": ["independent read/write optimization"], "tradeoffs": ["consistency and duplication complexity"]},
            "ddd": {"definition": "Software structure follows domain language and bounded contexts.", "strengths": ["business-focused boundaries"], "tradeoffs": ["requires sustained domain collaboration"]},
            "client_server": {"definition": "Clients request capabilities from a server boundary.", "strengths": ["centralized services and multiple clients"], "tradeoffs": ["network availability and latency"]},
            "distributed_systems": {"definition": "Components coordinate across process or network boundaries.", "strengths": ["scale and fault isolation"], "tradeoffs": ["partial failure and coordination complexity"]},
        }

    @staticmethod
    def git_profiles() -> dict[str, dict[str, Any]]:
        return {
            "repositories": {"definition": "Versioned project history and objects stored in Git.", "uses": ["source history", "collaboration"], "risks": ["incomplete repository context"]},
            "branches": {"definition": "Movable names pointing to lines of commit history.", "uses": ["parallel work", "release isolation"], "risks": ["stale or diverging branches"]},
            "commits": {"definition": "Snapshots with parent links and metadata.", "uses": ["traceable changes", "review"], "risks": ["unclear messages or oversized changes"]},
            "merge": {"definition": "Combines histories through a commit with multiple parents.", "uses": ["integrating branches"], "risks": ["conflicts and unrelated changes"]},
            "rebase": {"definition": "Replays commits onto another base and rewrites ancestry.", "uses": ["linear local history"], "risks": ["rewritten shared history"]},
            "cherry_pick": {"definition": "Applies one existing change onto another branch.", "uses": ["selective backports"], "risks": ["missing dependencies and duplicates"]},
            "stash": {"definition": "Stores uncommitted work aside temporarily.", "uses": ["context switching"], "risks": ["lost or forgotten work"]},
            "tags": {"definition": "Named references to repository objects.", "uses": ["releases", "milestones"], "risks": ["moving or unsigned release markers"]},
            "conflicts": {"definition": "Overlapping changes Git cannot reconcile automatically.", "uses": ["merge/rebase resolution"], "risks": ["incorrect manual resolution"]},
            "diffs": {"definition": "Line-level representation of changes between states.", "uses": ["review", "impact analysis"], "risks": ["missing generated or untracked files"]},
            "history": {"definition": "Reachable graph of commits and parent relationships.", "uses": ["forensics", "traceability"], "risks": ["shallow or rewritten history"]},
            "revert": {"definition": "A new commit that reverses an earlier change.", "uses": ["safe rollback of published history"], "risks": ["follow-up conflicts"]},
            "reset": {"definition": "Moves a branch reference and optionally changes index/worktree.", "uses": ["local cleanup"], "risks": ["discarding commits or work"]},
            "pull": {"definition": "Fetches remote history and integrates it locally.", "uses": ["synchronization"], "risks": ["unexpected merge/rebase results"]},
            "push": {"definition": "Publishes local refs and commits to a remote.", "uses": ["collaboration"], "risks": ["unreviewed or sensitive changes"]},
            "pull_requests": {"definition": "A review and integration proposal between repository refs.", "uses": ["review", "controlled merging"], "risks": ["stale branch or incomplete checks"]},
        }

    @staticmethod
    def testing_profiles() -> dict[str, dict[str, Any]]:
        return {
            "unit_tests": {"definition": "Tests a small unit in isolation.", "uses": ["pure logic", "class behavior"], "risks": ["over-mocking implementation details"]},
            "feature_tests": {"definition": "Tests an application feature through its public boundary.", "uses": ["HTTP/application behavior"], "risks": ["brittle environment coupling"]},
            "integration_tests": {"definition": "Tests collaboration between real components.", "uses": ["database and service contracts"], "risks": ["slow or environment-dependent execution"]},
            "end_to_end": {"definition": "Tests a user-visible flow across the deployed system boundary.", "uses": ["critical journeys"], "risks": ["flakiness and expensive diagnosis"]},
            "regression": {"definition": "Re-executes checks for behavior previously fixed or supported.", "uses": ["change safety"], "risks": ["stale coverage if assertions lose meaning"]},
            "mocks": {"definition": "Programmable test doubles that verify or control interactions.", "uses": ["isolating collaborators"], "risks": ["testing implementation instead of behavior"]},
            "stubs": {"definition": "Test doubles that provide predetermined responses.", "uses": ["controlled inputs"], "risks": ["unrealistic responses"]},
            "fakes": {"definition": "Simplified working implementations used in tests.", "uses": ["in-memory persistence"], "risks": ["behavior diverging from production"]},
            "fixtures": {"definition": "Reusable test setup data or environment.", "uses": ["repeatable scenarios"], "risks": ["hidden coupling and oversized setup"]},
            "assertions": {"definition": "Checks that compare observed behavior with an expected contract.", "uses": ["test verdicts"], "risks": ["weak or overly broad assertions"]},
            "test_doubles": {"definition": "General category for mocks, stubs, spies, and fakes.", "uses": ["controlled collaboration"], "risks": ["false confidence"]},
            "coverage": {"definition": "Measurement of executed code or branch space by tests.", "uses": ["finding untested areas"], "risks": ["coverage percentage is not correctness"]},
            "tdd": {"definition": "Develops behavior through failing test, implementation, and refactoring cycles.", "uses": ["incremental design"], "risks": ["tests coupled to design"]},
            "bdd": {"definition": "Describes behavior in examples shared by technical and domain participants.", "uses": ["acceptance criteria"], "risks": ["ceremonial scenarios without meaningful assertions"]},
        }

    @staticmethod
    def html_profiles() -> dict[str, dict[str, Any]]:
        return {
            "semantic_html": {"definition": "HTML elements communicate the meaning and role of content.", "uses": ["structure", "accessibility", "maintainability"], "risks": ["div-only markup loses native semantics"]},
            "document_structure": {"definition": "The html, head and body regions organize a valid document.", "uses": ["browser parsing", "metadata placement"], "risks": ["malformed structure changes interpretation"]},
            "forms": {"definition": "Forms group controls and submit user-provided values.", "uses": ["data entry", "actions"], "risks": ["missing names or labels lose data and context"]},
            "inputs": {"definition": "Form controls collect typed user input.", "uses": ["validation", "native interaction"], "risks": ["wrong type or missing accessible name"]},
            "tables": {"definition": "Tables represent two-dimensional tabular data.", "uses": ["reports", "comparisons"], "risks": ["layout tables and missing headers harm comprehension"]},
            "links": {"definition": "Anchors navigate to a resource or document location.", "uses": ["navigation", "deep links"], "risks": ["actions disguised as links or missing href"]},
            "media": {"definition": "Images, audio and video embed non-text content.", "uses": ["communication", "rich content"], "risks": ["missing alternatives, captions or controls"]},
            "accessibility": {"definition": "Markup remains usable by people with diverse abilities and assistive technology.", "uses": ["inclusive interfaces"], "risks": ["unlabeled controls and inaccessible interaction"]},
            "aria": {"definition": "ARIA adds roles, states and properties when native HTML cannot express the interface.", "uses": ["custom widgets"], "risks": ["redundant or incorrect ARIA overrides native semantics"]},
            "seo_basics": {"definition": "Basic page signals help search engines understand a document.", "uses": ["discoverability"], "risks": ["missing title or misleading metadata"]},
            "metadata": {"definition": "Head elements describe the document and its processing hints.", "uses": ["title", "description", "viewport"], "risks": ["metadata in body or contradictory values"]},
            "html5_apis": {"definition": "Browser APIs extend HTML documents with capabilities such as storage, canvas and history.", "uses": ["rich web applications"], "risks": ["privacy, compatibility and progressive-enhancement gaps"]},
        }

    @staticmethod
    def css_profiles() -> dict[str, dict[str, Any]]:
        return {
            "selectors": {"definition": "Selectors choose the elements to which declarations apply.", "uses": ["targeting styles"], "risks": ["overly broad or brittle selectors"]},
            "specificity": {"definition": "A selector weight used to resolve competing declarations.", "uses": ["predictable overrides"], "risks": ["specificity wars"]},
            "cascade": {"definition": "The ordered resolution of origin, layers, specificity and source order.", "uses": ["composable styles"], "risks": ["unexpected overrides and important overuse"]},
            "inheritance": {"definition": "Some properties flow from an ancestor to descendants.", "uses": ["shared typography and color"], "risks": ["implicit styles"]},
            "box_model": {"definition": "Content, padding, border and margin determine an element's rendered box.", "uses": ["sizing and spacing"], "risks": ["unexpected dimensions without box-sizing"]},
            "display": {"definition": "Controls an element's participation in layout and formatting context.", "uses": ["block, inline, flex and grid layout"], "risks": ["hidden or collapsed content"]},
            "position": {"definition": "Controls an element's placement relative to normal flow or a containing block.", "uses": ["overlays", "sticky navigation"], "risks": ["overlap and inaccessible off-screen content"]},
            "flexbox": {"definition": "One-dimensional layout for distributing and aligning items along an axis.", "uses": ["toolbars", "rows and columns"], "risks": ["shrink and overflow surprises"]},
            "grid": {"definition": "Two-dimensional layout using explicit rows and columns.", "uses": ["page and card layouts"], "risks": ["implicit tracks and dense placement"]},
            "responsive_design": {"definition": "Layouts adapt to viewport and device constraints.", "uses": ["mobile and desktop experiences"], "risks": ["fixed widths and untested breakpoints"]},
            "media_queries": {"definition": "Conditional rules based on viewport or user/device features.", "uses": ["responsive and preference-aware styles"], "risks": ["conflicting breakpoints"]},
            "animations": {"definition": "Keyframe-driven changes over time.", "uses": ["feedback and emphasis"], "risks": ["motion sickness and performance cost"]},
            "transitions": {"definition": "Interpolates property changes between states.", "uses": ["hover and focus feedback"], "risks": ["missing reduced-motion handling"]},
            "pseudo_elements": {"definition": "Generated boxes such as ::before and ::after.", "uses": ["decoration and indicators"], "risks": ["content unavailable to assistive technology"]},
            "variables": {"definition": "Custom properties reused through var().", "uses": ["themes and design tokens"], "risks": ["undefined fallback values"]},
            "nesting": {"definition": "Nested rules express selector relationships in modern CSS.", "uses": ["component-local styles"], "risks": ["unexpected selector expansion"]},
            "modern_css": {"definition": "Current CSS capabilities such as clamp, container queries, subgrid and cascade layers.", "uses": ["adaptive and maintainable design"], "risks": ["browser support gaps"]},
        }

    @staticmethod
    def javascript_profiles() -> dict[str, dict[str, Any]]:
        return {
            "variables": {"definition": "Bindings declared with var, let or const.", "uses": ["state and values"], "risks": ["hoisting, reassignment and accidental mutation"]},
            "scope": {"definition": "The set of bindings visible at a point in a program.", "uses": ["encapsulation and name resolution"], "risks": ["shadowing and leaked state"]},
            "closures": {"definition": "A function retaining access to its lexical environment.", "uses": ["callbacks", "factories", "private state"], "risks": ["retained memory and stale values"]},
            "functions": {"definition": "Reusable callable behavior.", "uses": ["composition", "event handlers"], "risks": ["implicit context and side effects"]},
            "objects": {"definition": "Key-value entities used to model state and behavior.", "uses": ["records", "APIs"], "risks": ["shared mutation and prototype surprises"]},
            "arrays": {"definition": "Ordered indexed collections.", "uses": ["sequences", "batch operations"], "risks": ["mutation and inefficient repeated scans"]},
            "classes": {"definition": "Syntax for constructing objects and expressing instance behavior.", "uses": ["domain models", "components"], "risks": ["deep inheritance and hidden state"]},
            "prototypes": {"definition": "The delegation chain used by JavaScript objects.", "uses": ["inheritance and shared methods"], "risks": ["prototype pollution and confusing lookup"]},
            "modules": {"definition": "Files with explicit import and export boundaries.", "uses": ["dependency organization"], "risks": ["cycles and oversized modules"]},
            "promises": {"definition": "Objects representing eventual completion or failure.", "uses": ["async composition"], "risks": ["unhandled rejection and accidental floating promises"]},
            "async_await": {"definition": "Syntax for expressing promise-based asynchronous control flow.", "uses": ["readable async workflows"], "risks": ["serializing independent work"]},
            "events": {"definition": "Signals delivered to registered listeners.", "uses": ["UI and integration boundaries"], "risks": ["leaks, ordering and duplicate handlers"]},
            "dom": {"definition": "The object representation of a document manipulated by scripts.", "uses": ["browser UI updates"], "risks": ["layout cost and unsafe HTML injection"]},
            "fetch": {"definition": "Promise-based browser API for HTTP requests.", "uses": ["network communication"], "risks": ["rejections, non-OK responses and cancellation"]},
            "error_handling": {"definition": "Explicit detection, propagation and recovery from failures.", "uses": ["reliable boundaries"], "risks": ["swallowed or incomplete errors"]},
            "destructuring": {"definition": "Binding values from object properties or array positions.", "uses": ["clear parameter extraction"], "risks": ["undefined assumptions"]},
            "spread": {"definition": "Expands iterable or object values into a new expression.", "uses": ["copying and composition"], "risks": ["shallow copies and unintended overrides"]},
            "iterators": {"definition": "Protocol for producing values sequentially.", "uses": ["lazy traversal"], "risks": ["infinite or stateful iteration"]},
            "generators": {"definition": "Functions that pause and resume while yielding values.", "uses": ["lazy sequences", "cooperative workflows"], "risks": ["complex control flow"]},
            "map": {"definition": "Key-value collection with explicit key identity.", "uses": ["indexed lookup"], "risks": ["retained references and key confusion"]},
            "set": {"definition": "Collection of unique values.", "uses": ["deduplication", "membership"], "risks": ["identity semantics"]},
        }

    @staticmethod
    def php_profiles() -> dict[str, dict[str, Any]]:
        return {
            "syntax": {"definition": "PHP language delimiters, expressions and statements.", "uses": ["executable server code"], "risks": ["parse errors and ambiguous coercion"]},
            "types": {"definition": "Scalar, compound, union and return type contracts.", "uses": ["runtime contracts"], "risks": ["coercion and incomplete boundaries"]},
            "functions": {"definition": "Named callable units with parameters and return behavior.", "uses": ["composition"], "risks": ["hidden side effects and weak contracts"]},
            "arrays": {"definition": "Ordered maps supporting indexed and associative data.", "uses": ["collections and records"], "risks": ["mixed shape and inefficient scans"]},
            "objects": {"definition": "Instances containing state and behavior.", "uses": ["domain models"], "risks": ["shared mutation and lifecycle confusion"]},
            "classes": {"definition": "Types defining object structure and behavior.", "uses": ["encapsulation"], "risks": ["large classes and inheritance coupling"]},
            "interfaces": {"definition": "Behavioral contracts implemented by classes.", "uses": ["substitution and dependency inversion"], "risks": ["leaky or oversized contracts"]},
            "traits": {"definition": "Reusable method and property composition units.", "uses": ["horizontal reuse"], "risks": ["hidden coupling and conflicts"]},
            "enums": {"definition": "Closed sets of named cases, optionally backed by values.", "uses": ["domain state"], "risks": ["invalid serialization assumptions"]},
            "namespaces": {"definition": "Qualified symbol boundaries that prevent naming collisions.", "uses": ["module organization"], "risks": ["ambiguous imports and class aliases"]},
            "exceptions": {"definition": "Objects representing exceptional control flow.", "uses": ["failure propagation"], "risks": ["swallowed errors and overly broad catches"]},
            "attributes": {"definition": "Structured metadata attached to PHP declarations.", "uses": ["framework configuration", "mapping"], "risks": ["hidden behavior and reflection cost"]},
            "closures": {"definition": "Anonymous functions capturing lexical variables.", "uses": ["callbacks", "configuration"], "risks": ["retained state and unclear capture"]},
            "generators": {"definition": "Functions yielding values lazily instead of materializing collections.", "uses": ["streaming and large traversals"], "risks": ["one-shot state and hidden I/O"]},
            "iterators": {"definition": "Contracts for traversing values sequentially.", "uses": ["custom collections"], "risks": ["invalid cursor state"]},
            "dependency_injection": {"definition": "Supplying collaborators from outside a class.", "uses": ["testability and loose coupling"], "risks": ["service locator and hidden dependencies"]},
            "reflection": {"definition": "Runtime inspection of classes, methods and attributes.", "uses": ["framework discovery"], "risks": ["runtime fragility and performance cost"]},
            "filesystem": {"definition": "Reading, writing and resolving files and paths.", "uses": ["uploads and persistence"], "risks": ["traversal, permissions and race conditions"]},
            "sessions": {"definition": "Server-side state associated with a client session.", "uses": ["authentication and workflows"], "risks": ["fixation, theft and stale state"]},
            "security": {"definition": "Controls protecting code, data, identity and boundaries.", "uses": ["safe web applications"], "risks": ["injection, disclosure and weak secrets"]},
        }

    @staticmethod
    def advanced_php_profiles() -> dict[str, dict[str, Any]]:
        return {
            "memory": {"definition": "Runtime allocation, garbage collection and bounded memory usage.", "uses": ["large workloads"], "risks": ["exhaustion and retention"]},
            "references": {"definition": "Aliases to storage locations rather than copied values.", "uses": ["in-place mutation"], "risks": ["unexpected aliasing"]},
            "autoloading": {"definition": "Resolving classes on demand through registered loaders.", "uses": ["modular applications"], "risks": ["order and duplicate loader conflicts"]},
            "composer": {"definition": "PHP dependency and autoload metadata managed by Composer.", "uses": ["repeatable dependency installation"], "risks": ["drift and missing mappings"]},
            "psr": {"definition": "PHP-FIG interoperability recommendations and interfaces.", "uses": ["shared framework contracts"], "risks": ["partial or inconsistent adoption"]},
            "spl": {"definition": "Standard PHP Library collections, iterators and utilities.", "uses": ["efficient traversal and structures"], "risks": ["iterator state assumptions"]},
            "reflection": {"definition": "Runtime inspection of declarations and metadata.", "uses": ["framework discovery"], "risks": ["runtime cost and hidden coupling"]},
            "attributes": {"definition": "Structured metadata attached to PHP declarations.", "uses": ["routing and mapping"], "risks": ["unvalidated configuration"]},
            "serialization": {"definition": "Encoding and restoring PHP values across boundaries.", "uses": ["queues and persistence"], "risks": ["object injection and schema drift"]},
            "streams": {"definition": "Uniform I/O abstraction for files, network resources and wrappers.", "uses": ["incremental I/O"], "risks": ["blocking and unbounded reads"]},
            "processes": {"definition": "Launching and controlling external operating-system processes.", "uses": ["workers and tooling"], "risks": ["injection, leaks and orphaned children"]},
            "cli": {"definition": "PHP command-line runtime inputs, outputs and exit contracts.", "uses": ["automation and workers"], "risks": ["interactive assumptions"]},
            "environment": {"definition": "Runtime and deployment configuration supplied outside source code.", "uses": ["secrets and environment-specific behavior"], "risks": ["disclosure and configuration drift"]},
            "performance": {"definition": "Measured CPU, memory and I/O behavior of PHP workloads.", "uses": ["capacity and latency work"], "risks": ["premature optimization"]},
            "opcache": {"definition": "Bytecode caching and optimization for PHP execution.", "uses": ["production throughput"], "risks": ["stale code and disabled caching"]},
            "error_handling": {"definition": "Conversion, reporting and recovery of runtime failures.", "uses": ["operational diagnosability"], "risks": ["suppression and inconsistent recovery"]},
        }

    @staticmethod
    def browser_profiles() -> dict[str, dict[str, Any]]:
        return {
            "dom": {"definition": "The browser's live object model of the document.", "uses": ["UI updates", "event wiring"], "risks": ["stale nodes and expensive mutations"]},
            "rendering": {"definition": "The browser pipeline that turns document, styles and scripts into pixels.", "uses": ["visual output"], "risks": ["layout thrashing and dropped frames"]},
            "event_loop": {"definition": "The scheduling model for tasks, microtasks and rendering opportunities.", "uses": ["async coordination"], "risks": ["blocking the main thread and starvation"]},
            "browser_storage": {"definition": "Client-side persistence mechanisms available to an origin.", "uses": ["preferences", "offline state"], "risks": ["quota, privacy and stale data"]},
            "cookies": {"definition": "Small name-value data sent according to cookie scope and attributes.", "uses": ["sessions", "server state"], "risks": ["CSRF, theft and incorrect SameSite policy"]},
            "local_storage": {"definition": "Synchronous origin-scoped key-value storage that persists across sessions.", "uses": ["small preferences"], "risks": ["blocking and sensitive-data exposure"]},
            "session_storage": {"definition": "Synchronous storage scoped to an origin and browsing tab.", "uses": ["tab-local state"], "risks": ["sensitive-data exposure and quota"]},
            "fetch": {"definition": "Promise-based browser network request API.", "uses": ["HTTP communication"], "risks": ["CORS, aborts and non-OK responses"]},
            "network_requests": {"definition": "Browser-originated HTTP or real-time communication.", "uses": ["APIs and streams"], "risks": ["timeouts, retries and connectivity changes"]},
            "caching": {"definition": "Reuse of previously retrieved resources or responses.", "uses": ["performance", "offline behavior"], "risks": ["stale or incorrectly scoped data"]},
            "cors": {"definition": "Server-controlled rules for cross-origin browser requests.", "uses": ["trusted frontend/API boundaries"], "risks": ["blocked requests or overbroad origins"]},
            "browser_security": {"definition": "Origin, isolation and content protections enforced by the browser.", "uses": ["confidentiality and integrity"], "risks": ["XSS, CSRF and unsafe embeds"]},
            "lifecycle": {"definition": "Page, document and component visibility, loading and disposal transitions.", "uses": ["resource management"], "risks": ["leaked listeners, timers and requests"]},
        }

    @staticmethod
    def tailwind_profiles() -> dict[str, dict[str, Any]]:
        return {
            "utility_classes": {"definition": "Small single-purpose classes compose styles directly in markup.", "uses": ["rapid UI composition"], "risks": ["long or conflicting class lists"]},
            "responsive_variants": {"definition": "Breakpoint prefixes apply utilities at responsive widths.", "uses": ["mobile-first layouts"], "risks": ["missing base behavior and breakpoint conflicts"]},
            "states": {"definition": "Variants such as hover, focus and disabled style interaction states.", "uses": ["feedback and accessibility"], "risks": ["missing keyboard states"]},
            "dark_mode": {"definition": "Dark variants adapt components to a dark color scheme or selector.", "uses": ["theme switching"], "risks": ["contrast and incomplete coverage"]},
            "spacing": {"definition": "Padding, margin, gap and space utilities express a spacing scale.", "uses": ["consistent layout rhythm"], "risks": ["contradictory utilities"]},
            "typography": {"definition": "Text size, weight, leading, tracking and font utilities.", "uses": ["hierarchy and readability"], "risks": ["contrast and inconsistent scale"]},
            "flex": {"definition": "Flexbox utilities configure one-dimensional layout.", "uses": ["alignment and distribution"], "risks": ["shrink and overflow surprises"]},
            "grid": {"definition": "Grid utilities configure rows, columns and placement.", "uses": ["two-dimensional layouts"], "risks": ["implicit tracks and breakpoint gaps"]},
            "positioning": {"definition": "Position and inset utilities place elements relative to a containing block.", "uses": ["overlays and sticky UI"], "risks": ["overlap and stacking issues"]},
            "colors": {"definition": "Text, background, border and ring color utilities.", "uses": ["visual hierarchy and states"], "risks": ["contrast and contradictory colors"]},
            "sizing": {"definition": "Width, height and constraint utilities.", "uses": ["responsive dimensions"], "risks": ["fixed layouts and overflow"]},
            "arbitrary_values": {"definition": "Bracket syntax supplies one-off values such as w-[...] or bg-[#...].", "uses": ["exceptional design requirements"], "risks": ["escaping the design system"]},
            "configuration": {"definition": "Tailwind theme, content and core behavior configuration.", "uses": ["tokens and build scanning"], "risks": ["missing content paths and stale tokens"]},
            "plugins": {"definition": "Extensions that add utilities, components or variants.", "uses": ["project-specific conventions"], "risks": ["build complexity and undocumented behavior"]},
            "component_patterns": {"definition": "Reusable combinations of utilities represented by components or extracted classes.", "uses": ["consistency and reuse"], "risks": ["duplicated long class lists"]},
        }

    @staticmethod
    def bootstrap_profiles() -> dict[str, dict[str, Any]]:
        return {
            "bootstrap_detection": {"definition": "Evidence that Bootstrap classes, assets or configuration are used.", "uses": ["framework identification"], "risks": ["class-name overlap with custom CSS"]},
            "grid": {"definition": "Bootstrap's row, column and gutter layout system.", "uses": ["responsive page structure"], "risks": ["columns outside rows or conflicting widths"]},
            "containers": {"definition": "Responsive max-width wrappers for page content.", "uses": ["readable content width"], "risks": ["nested or missing container boundaries"]},
            "breakpoints": {"definition": "Named viewport ranges used by responsive Bootstrap classes.", "uses": ["mobile-first adaptation"], "risks": ["unexpected defaults between breakpoints"]},
            "utilities": {"definition": "Single-purpose Bootstrap classes for display, spacing, color and sizing.", "uses": ["local adjustments"], "risks": ["long lists and conflicting intent"]},
            "components": {"definition": "Structured Bootstrap UI patterns with documented markup.", "uses": ["consistent interface building"], "risks": ["missing required child structure"]},
            "forms": {"definition": "Bootstrap styles and layout for form controls.", "uses": ["data entry"], "risks": ["missing labels and validation state"]},
            "buttons": {"definition": "Action controls using btn and contextual variants.", "uses": ["user actions"], "risks": ["wrong element or unclear state"]},
            "modals": {"definition": "Dialog overlays with Bootstrap markup and JavaScript behavior.", "uses": ["focused interactions"], "risks": ["missing trigger, focus or dismissal wiring"]},
            "navbar": {"definition": "Responsive navigation component with collapse behavior.", "uses": ["site navigation"], "risks": ["missing toggler target or keyboard behavior"]},
            "cards": {"definition": "Flexible content containers with card sections and media.", "uses": ["content summaries"], "risks": ["inconsistent hierarchy"]},
            "responsive_utilities": {"definition": "Breakpoint-qualified display, spacing and alignment utilities.", "uses": ["responsive adjustments"], "risks": ["unverified intermediate states"]},
            "customization": {"definition": "Sass variables, theme maps or extension points changing Bootstrap defaults.", "uses": ["brand tokens and custom components"], "risks": ["upgrade drift and undocumented overrides"]},
            "javascript_components": {"definition": "Bootstrap interactive components initialized through data attributes or JavaScript.", "uses": ["modals, collapse, dropdowns"], "risks": ["version mismatch or missing bundle"]},
        }

    @staticmethod
    def react_profiles() -> dict[str, dict[str, Any]]:
        return {
            "components": {"definition": "Reusable units that return UI and behavior.", "uses": ["composition", "reuse"], "risks": ["large components and hidden side effects"]},
            "jsx": {"definition": "JavaScript syntax for describing element trees.", "uses": ["declarative rendering"], "risks": ["unsafe dynamic HTML and confusing expressions"]},
            "props": {"definition": "Inputs passed from a parent to a component.", "uses": ["data and callbacks"], "risks": ["prop drilling and mutation"]},
            "state": {"definition": "Component-owned data that causes rendering updates.", "uses": ["interactive UI"], "risks": ["duplicated or derived state"]},
            "hooks": {"definition": "Functions that connect components to React state and lifecycle behavior.", "uses": ["functional components"], "risks": ["violating hook ordering rules"]},
            "useState": {"definition": "Hook for local state values.", "uses": ["small interactive state"], "risks": ["stale updates and excessive state"]},
            "useEffect": {"definition": "Hook for synchronizing with external systems after rendering.", "uses": ["subscriptions", "requests"], "risks": ["loops, stale dependencies and leaks"]},
            "useMemo": {"definition": "Hook that memoizes a calculated value.", "uses": ["expensive calculations"], "risks": ["unnecessary complexity and stale values"]},
            "useCallback": {"definition": "Hook that memoizes a function identity.", "uses": ["memoized children and effect dependencies"], "risks": ["premature optimization"]},
            "useRef": {"definition": "Hook for a stable mutable reference that does not trigger rendering.", "uses": ["DOM refs", "instance values"], "risks": ["imperative escape hatches"]},
            "context": {"definition": "A tree-level mechanism for sharing values without explicit prop passing.", "uses": ["theme", "session", "configuration"], "risks": ["broad rerenders and hidden dependencies"]},
            "reducers": {"definition": "State transitions represented by actions and a reducer function.", "uses": ["complex local state"], "risks": ["overengineering and invalid transitions"]},
            "forms": {"definition": "Controlled or uncontrolled inputs coordinated by a form boundary.", "uses": ["data entry and validation"], "risks": ["state duplication and missing names"]},
            "events": {"definition": "React event props and browser events handled by components.", "uses": ["interaction"], "risks": ["stale closures and handler churn"]},
            "rendering": {"definition": "React turns component output into a rendered tree.", "uses": ["UI updates"], "risks": ["expensive render work"]},
            "reconciliation": {"definition": "React compares trees and uses identity to update the rendered result.", "uses": ["efficient updates"], "risks": ["unstable or missing keys"]},
            "component_lifecycle": {"definition": "Mount, update and unmount phases of component behavior.", "uses": ["resource ownership"], "risks": ["cleanup omissions"]},
            "routing": {"definition": "Mapping URL state to rendered routes and navigation.", "uses": ["multi-view applications"], "risks": ["unhandled routes and state loss"]},
            "state_management": {"definition": "Patterns or libraries coordinating state across component boundaries.", "uses": ["shared application state"], "risks": ["global coupling and excessive rerenders"]},
        }

    @staticmethod
    def vue_profiles() -> dict[str, dict[str, Any]]:
        return {
            "components": {"definition": "Reusable Vue units with template, state and behavior.", "uses": ["composition", "reuse"], "risks": ["large components and implicit coupling"]},
            "props": {"definition": "Read-only inputs passed from a parent component.", "uses": ["component contracts"], "risks": ["mutation and prop drilling"]},
            "emits": {"definition": "Events a component sends to its parent.", "uses": ["child-to-parent communication"], "risks": ["undeclared or ambiguous event contracts"]},
            "reactive_state": {"definition": "State tracked by Vue's reactivity system.", "uses": ["interactive UI"], "risks": ["untracked mutations and duplicated state"]},
            "computed": {"definition": "Cached values derived from reactive dependencies.", "uses": ["derived presentation state"], "risks": ["side effects and circular dependencies"]},
            "watchers": {"definition": "Reactions to changes in reactive sources.", "uses": ["external synchronization"], "risks": ["loops, deep cost and missing cleanup"]},
            "lifecycle": {"definition": "Hooks around component creation, mounting, updates and disposal.", "uses": ["resource ownership"], "risks": ["listeners and requests outliving components"]},
            "directives": {"definition": "Special attributes that apply behavior to DOM elements.", "uses": ["DOM integration"], "risks": ["imperative coupling"]},
            "slots": {"definition": "Template outlets for parent-provided content.", "uses": ["layout composition"], "risks": ["implicit content contracts"]},
            "composables": {"definition": "Reusable Composition API functions that encapsulate reactive logic.", "uses": ["shared behavior"], "risks": ["hidden side effects and lifecycle assumptions"]},
            "vue_router": {"definition": "URL-to-component routing for Vue applications.", "uses": ["multi-view apps"], "risks": ["unguarded routes and state loss"]},
            "pinia": {"definition": "Vue store library for shared application state.", "uses": ["cross-component state"], "risks": ["global coupling and oversized stores"]},
            "composition_api": {"definition": "Vue API organized around setup functions and composables.", "uses": ["logic reuse and type inference"], "risks": ["unclear ownership of effects"]},
            "options_api": {"definition": "Vue API organized into data, methods, computed, watch and lifecycle options.", "uses": ["structured component definitions"], "risks": ["logic scattered across options"]},
            "vue_version_2": {"definition": "Evidence of Vue 2 constructor or Options API lifecycle conventions.", "uses": ["legacy Vue applications"], "risks": ["migration and compatibility constraints"]},
            "vue_version_3": {"definition": "Evidence of Vue 3 app creation or Composition API features.", "uses": ["modern Vue applications"], "risks": ["mixed migration boundaries"]},
        }

    @staticmethod
    def quasar_profiles() -> dict[str, dict[str, Any]]:
        return {
            "quasar_cli": {"definition": "Quasar project tooling for development, builds and target modes.", "uses": ["SPA, PWA and native targets"], "risks": ["configuration drift and version mismatch"]},
            "components": {"definition": "Quasar Q-prefixed UI components.", "uses": ["consistent Material-like UI"], "risks": ["missing required props or slots"]},
            "layouts": {"definition": "QLayout-based application shell with headers, drawers and page containers.", "uses": ["shared application chrome"], "risks": ["incorrect nesting or route outlet placement"]},
            "pages": {"definition": "Route-level views rendered inside the Quasar layout.", "uses": ["application screens"], "risks": ["missing routes and duplicated page logic"]},
            "boot_files": {"definition": "Startup modules registered by Quasar before application mounting.", "uses": ["axios, auth and global setup"], "risks": ["order, SSR safety and hidden side effects"]},
            "plugins": {"definition": "Reusable Quasar or Vue plugins installed into the application.", "uses": ["global services and UI integrations"], "risks": ["duplicate registration and bundle cost"]},
            "directives": {"definition": "Vue or Quasar directives that attach behavior to elements.", "uses": ["ripple, popup and touch interactions"], "risks": ["imperative coupling and missing cleanup"]},
            "qtable": {"definition": "Quasar data table with rows, columns and interaction contracts.", "uses": ["tabular data"], "risks": ["missing row identity, pagination or loading state"]},
            "qform": {"definition": "Quasar form container coordinating validation and submission.", "uses": ["validated forms"], "risks": ["missing rules or submit handling"]},
            "dialogs": {"definition": "Modal dialog component or Dialog plugin service.", "uses": ["confirmation and focused workflows"], "risks": ["missing dismissal and focus management"]},
            "notifications": {"definition": "Quasar Notify service for transient feedback.", "uses": ["success and error feedback"], "risks": ["overuse and inaccessible messages"]},
            "routing": {"definition": "Vue Router integration used by Quasar pages and layouts.", "uses": ["navigation"], "risks": ["unguarded or missing routes"]},
            "pinia": {"definition": "Pinia store integration for shared Quasar application state.", "uses": ["cross-page state"], "risks": ["global coupling"]},
            "spa": {"definition": "Single-page application build target.", "uses": ["browser application"], "risks": ["fallback and deployment base URL issues"]},
            "pwa": {"definition": "Progressive Web App build target with service worker behavior.", "uses": ["offline and installable apps"], "risks": ["stale caches and missing registration"]},
            "capacitor": {"definition": "Native mobile packaging target through Capacitor.", "uses": ["Android and iOS applications"], "risks": ["native plugin and platform drift"]},
            "electron": {"definition": "Desktop packaging target through Electron.", "uses": ["desktop applications"], "risks": ["preload security and platform differences"]},
            "configuration": {"definition": "quasar.config configuration for framework, build and target behavior.", "uses": ["project customization"], "risks": ["mode-specific configuration gaps"]},
        }

    @staticmethod
    def frontend_architecture_profiles() -> dict[str, dict[str, Any]]:
        return {
            "component_architecture": {"definition": "Decomposition of the interface into components with explicit responsibilities and composition boundaries.", "uses": ["isolating UI behavior", "parallel development"], "risks": ["god components and hidden coupling"]},
            "state_management": {"definition": "Ownership, transition and sharing model for client-side state.", "uses": ["interactive workflows", "shared session state"], "risks": ["duplicated or stale state"]},
            "routing": {"definition": "Mapping between URLs, navigation transitions and rendered screens.", "uses": ["deep links", "navigation"], "risks": ["unprotected routes and inconsistent data loading"]},
            "api_layers": {"definition": "Boundary encapsulating transport, serialization, retries and server contracts.", "uses": ["decoupling views from HTTP"], "risks": ["requests scattered through components"]},
            "composables_hooks": {"definition": "Reusable functions packaging stateful behavior and lifecycle integration.", "uses": ["sharing behavior"], "risks": ["implicit dependencies and lifecycle leaks"]},
            "reusable_components": {"definition": "UI building blocks designed for multiple consumers through stable props, events and slots.", "uses": ["consistency", "delivery speed"], "risks": ["over-generalized interfaces"]},
            "design_systems": {"definition": "Shared visual tokens, primitives and interaction patterns governing interface consistency.", "uses": ["brand consistency", "accessibility defaults"], "risks": ["token drift and undocumented overrides"]},
            "forms": {"definition": "Coordinated boundary for input state, submission and field feedback.", "uses": ["data entry"], "risks": ["uncontrolled submission and duplicated state"]},
            "validation": {"definition": "Rules and schemas defining acceptable input at field and domain boundaries.", "uses": ["preventing invalid writes"], "risks": ["client-only validation and divergent rules"]},
            "authentication": {"definition": "Frontend flow for establishing and maintaining user identity.", "uses": ["sessions", "token refresh"], "risks": ["token exposure and inconsistent session expiry"]},
            "authorization": {"definition": "Enforcement and presentation of permissions after identity is known.", "uses": ["protected routes and actions"], "risks": ["UI-only checks and privilege leaks"]},
            "error_handling": {"definition": "Consistent capture, classification and presentation of failures across UI and API boundaries.", "uses": ["recovery and support"], "risks": ["silent failures and inconsistent messages"]},
            "loading_states": {"definition": "Explicit pending, skeleton and transitional states while work is in progress.", "uses": ["feedback and perceived performance"], "risks": ["flicker and duplicate submissions"]},
            "caching": {"definition": "Policies for retaining, invalidating and reusing client-side data.", "uses": ["latency reduction"], "risks": ["stale data and incorrect invalidation"]},
            "responsive_architecture": {"definition": "Structural approach to adapting layout and interaction across viewport capabilities.", "uses": ["mobile and desktop experiences"], "risks": ["breakpoint-specific regressions"]},
        }

    @staticmethod
    def pattern_profiles() -> dict[str, dict[str, Any]]:
        return {
            "factory": {"definition": "Creates an object while hiding concrete construction.", "uses": ["varying product types", "centralized creation"], "risk": "can hide simple construction behind indirection"},
            "abstract_factory": {"definition": "Creates related families of compatible objects.", "uses": ["platform families", "matched products"], "risk": "adds many interfaces and concrete types"},
            "builder": {"definition": "Builds a complex object step by step.", "uses": ["many optional fields", "validated construction"], "risk": "unnecessary for simple objects"},
            "singleton": {"definition": "Restricts a type to one controlled instance.", "uses": ["explicit process-wide invariant"], "risk": "global state and hidden dependencies"},
            "adapter": {"definition": "Converts one interface into another expected by a client.", "uses": ["legacy integration", "third-party APIs"], "risk": "can hide incompatible semantics"},
            "decorator": {"definition": "Adds behavior around an object without changing its interface.", "uses": ["cross-cutting behavior", "layered policies"], "risk": "many wrappers obscure call flow"},
            "facade": {"definition": "Provides a simpler boundary over a subsystem.", "uses": ["stable application boundary", "complex integrations"], "risk": "facade can become a god object"},
            "proxy": {"definition": "Controls access to another object.", "uses": ["caching", "authorization", "remote access"], "risk": "latency and behavior may be hidden"},
            "strategy": {"definition": "Encapsulates interchangeable algorithms behind a common contract.", "uses": ["runtime algorithm selection", "replaceable policies"], "risk": "overkill when there is only one algorithm"},
            "observer": {"definition": "Notifies subscribers when a subject changes.", "uses": ["events", "reactive updates"], "risk": "lifecycle leaks and hidden ordering"},
            "command": {"definition": "Represents an operation as an object.", "uses": ["queues", "undo/redo", "audit"], "risk": "many tiny classes"},
            "state": {"definition": "Changes behavior by delegating to a current state object.", "uses": ["explicit state machines"], "risk": "fragmentation for simple conditionals"},
            "repository": {"definition": "Abstracts persistence-oriented collection access.", "uses": ["domain isolation", "testable data access"], "risk": "duplicates ORM abstractions without value"},
            "service": {"definition": "Groups a cohesive domain operation that is not naturally owned by an entity.", "uses": ["application workflows", "domain operations"], "risk": "anemic catch-all service layer"},
            "dependency_injection": {"definition": "Supplies collaborators from outside a component.", "uses": ["replaceability", "testing", "runtime wiring"], "risk": "too many abstractions for stable local dependencies"},
        }

    @staticmethod
    def _attribute_names(node: ast.AST) -> set[str]:
        return {
            item.attr for item in ast.walk(node)
            if isinstance(item, ast.Attribute) and isinstance(item.value, ast.Name)
            and item.value.id == "self"
        }

    @staticmethod
    def paradigm_profiles() -> dict[str, ParadigmProfile]:
        profiles = [
            ("procedural", "Organizes behavior as procedures and explicit state transitions.", ("simple control flow", "low conceptual overhead"), ("shared mutable state can spread", "large systems can become tightly coupled"), ("scripts", "data processing", "systems utilities")),
            ("object_oriented", "Organizes state and behavior around objects.", ("localizes invariants", "models interacting entities"), ("indirection and complex hierarchies", "allocation overhead"), ("domain models", "frameworks", "large applications")),
            ("functional", "Builds behavior from functions and transformations.", ("testability", "composability", "fewer side effects"), ("learning curve", "allocation or recursion costs"), ("data pipelines", "concurrent transformations")),
            ("declarative", "Describes the desired result instead of every execution step.", ("concise intent", "optimization can be delegated"), ("less control over execution", "debugging can be indirect"), ("SQL", "configuration", "comprehensions")),
            ("event_driven", "Lets events trigger handlers or callbacks.", ("responsive decoupling", "natural UI and integration flow"), ("control flow is distributed", "ordering is harder to reason about"), ("GUIs", "webhooks", "message systems")),
            ("reactive", "Represents values or events as streams that propagate changes.", ("composable asynchronous flows", "backpressure and change propagation"), ("lifecycle complexity", "debugging time-dependent behavior"), ("UI state", "stream processing", "live data")),
            ("generic", "Defines algorithms over types or capabilities rather than one concrete type.", ("reuse", "compile-time constraints"), ("complex type errors", "over-generalization"), ("collections", "libraries", "type-safe algorithms")),
            ("composition", "Builds behavior by combining smaller components.", ("flexibility", "replaceable parts"), ("wiring can become verbose", "behavior may be indirect"), ("services", "pipelines", "dependency injection")),
            ("immutability", "Keeps values unchanged after creation.", ("easier reasoning", "safer sharing"), ("copying or allocation costs", "updates require new values"), ("concurrency", "value objects", "functional code")),
            ("encapsulation", "Hides representation behind a controlled interface.", ("protects invariants", "reduces coupling"), ("additional APIs and indirection"), ("libraries", "domain objects", "security boundaries")),
            ("abstraction", "Exposes essential behavior while hiding implementation details.", ("manages complexity", "enables substitution"), ("poor abstractions conceal important costs"), ("APIs", "interfaces", "architecture")),
            ("polymorphism", "Allows one interface to operate on multiple implementations.", ("extension without caller changes", "decoupled code"), ("dispatch complexity", "behavior may be surprising"), ("plugins", "adapters", "domain strategies")),
            ("inheritance", "Derives a type from a base type and reuses its contract or implementation.", ("reuse", "substitutability when designed well"), ("fragile base classes", "tight coupling"), ("framework extension", "is-a hierarchies")),
        ]
        return {
            item[0]: ParadigmProfile(item[0], item[1], item[2], item[3], item[4])
            for item in profiles
        }

    @staticmethod
    def default_concepts() -> list[FundamentalConcept]:
        definitions = [
            ("algorithms", "algorithms", "A finite, defined procedure that transforms input into output.", ("termination", "correctness", "complexity")),
            ("data_structures", "data structures", "An organization of data that defines access and update trade-offs.", ("array access is indexed", "hash lookup is average constant time", "structure choice affects memory")),
            ("arrays", "data structures", "A contiguous or indexed sequence with direct access by position.", ("index access is typically O(1)", "insertion in the middle may require shifting")),
            ("lists", "data structures", "An ordered collection whose access and update costs depend on its representation.", ("dynamic arrays favor indexed access", "linked lists favor local insertion with a node reference")),
            ("stacks", "data structures", "A last-in, first-out collection.", ("push and pop operate at one end", "used for call stacks and depth-first traversal")),
            ("queues", "data structures", "A first-in, first-out collection.", ("enqueue and dequeue preserve arrival order", "used for breadth-first traversal")),
            ("linked_lists", "data structures", "Nodes connected by references instead of contiguous indexing.", ("sequential lookup is O(n)", "node insertion can be O(1) with a predecessor reference")),
            ("hash_tables", "data structures", "A key-value structure using a hash function to select storage locations.", ("average lookup is O(1)", "collisions require a resolution strategy")),
            ("sets", "data structures", "A collection of unique values optimized for membership operations.", ("duplicates are removed", "average membership is O(1) with hashing")),
            ("trees", "data structures", "A hierarchical structure with parent-child relationships.", ("height controls many operation costs", "balanced trees improve worst-case lookup")),
            ("heaps", "data structures", "A priority-oriented tree representation supporting efficient extrema access.", ("root is minimum or maximum", "push and pop are O(log n)")),
            ("graphs", "data structures", "A set of vertices connected by edges.", ("representation may be adjacency list or matrix", "traversal cost is O(V + E) for adjacency lists")),
            ("recursion", "algorithms", "A function solving a problem through smaller calls to itself.", ("requires a base case", "stack usage grows with recursion depth")),
            ("searching", "algorithms", "Finding a target or satisfying element in a collection.", ("linear search is O(n)", "binary search requires ordered data and is O(log n)")),
            ("sorting", "algorithms", "Reordering values according to a comparison or key.", ("comparison sorting has an O(n log n) lower bound in common models", "stability can preserve equal-key order")),
            ("hashing", "algorithms", "Mapping a value to a repeatable fixed-range representation.", ("equal values must hash equally", "collisions are expected")),
            ("traversals", "algorithms", "Systematically visiting elements or nodes.", ("DFS uses stack or recursion", "BFS uses a queue")),
            ("dynamic_programming", "algorithms", "Solving overlapping subproblems by storing reusable results.", ("requires a state and recurrence", "memoization trades space for time")),
            ("greedy_algorithms", "algorithms", "Choosing the locally best option at each step.", ("requires a proof of greedy choice", "local choices are not universally optimal")),
            ("backtracking", "algorithms", "Exploring candidates and undoing choices when a branch fails.", ("maintains a partial solution", "worst-case search can be exponential")),
            ("time_complexity", "complexity", "How running time grows as input size grows.", ("Big O describes an upper growth bound", "nested independent loops often multiply costs")),
            ("space_complexity", "complexity", "How additional memory grows as input size grows.", ("input storage and auxiliary storage should be distinguished", "recursion may consume stack space")),
            ("memory", "systems", "Addressable storage used for code, data, stack, and dynamically allocated objects.", ("allocation has lifetime", "locality affects performance")),
            ("cpu", "systems", "The processor executes instructions and coordinates registers, caches, and cores.", ("latency differs from throughput", "parallel cores do not remove synchronization costs")),
            ("processes", "operating systems", "An isolated execution resource with its own virtual address space.", ("processes communicate through explicit IPC", "failure is isolated more strongly than threads")),
            ("threads", "operating systems", "Execution paths sharing a process address space.", ("shared state needs synchronization", "thread stacks remain separate")),
            ("concurrency", "systems", "Managing multiple tasks whose execution overlaps in time.", ("progress can interleave", "correctness requires coordination")),
            ("parallelism", "systems", "Executing multiple computations simultaneously on multiple execution units.", ("requires independent work", "speedup is limited by serial work")),
            ("input_output", "systems", "Exchange of data with files, devices, or external services.", ("I/O is often slower than CPU work", "buffering and backpressure matter")),
            ("file_systems", "systems", "Persistent organization of named data and metadata.", ("paths have scope and permissions", "durability is distinct from visibility")),
            ("networks", "systems", "Communication between endpoints over layered protocols.", ("latency and failure are normal", "serialization and timeouts are part of the contract")),
            ("compilation", "language_runtime", "Translation from source representation into a lower-level executable form.", ("compilers validate and transform", "optimization must preserve semantics")),
            ("interpretation", "language_runtime", "Executing source or an intermediate representation through an evaluator.", ("startup and portability trade off with execution overhead", "errors can be reported during execution")),
            ("runtime", "language_runtime", "The environment and services that execute a program.", ("lifetime, scheduling, and libraries shape behavior", "configuration is part of runtime behavior")),
            ("virtual_machines", "language_runtime", "A managed execution abstraction that runs bytecode or an emulated machine.", ("isolates instruction semantics", "garbage collection and JIT may be runtime services")),
            ("solid", "design principles", "A family of principles for maintainable object-oriented design.", ("single responsibility", "open/closed", "substitutability", "interface segregation", "dependency inversion")),
            ("dry", "design principles", "Avoiding duplicated knowledge or behavior that can diverge.", ("one source of truth", "duplication increases change cost")),
            ("kiss", "design principles", "Prefer the simplest design that correctly solves the problem.", ("simple code is easier to verify", "avoid accidental complexity")),
            ("yagni", "design principles", "Do not implement speculative functionality before it is needed.", ("defer unsupported requirements", "unused abstraction has maintenance cost")),
            ("interfaces", "object-oriented design", "A contract describing capabilities without prescribing implementation.", ("clients depend on behavior", "small interfaces reduce coupling")),
            ("abstract_classes", "object-oriented design", "Base classes that define shared contract or partial behavior for subclasses.", ("cannot represent complete concrete behavior", "subclasses must honor the contract")),
            ("traits", "object-oriented design", "Reusable behavior composed into classes without a primary is-a relationship.", ("keep reusable behavior focused", "avoid hidden state conflicts")),
            ("dependency_injection", "object-oriented design", "Supplying collaborators from outside rather than constructing them internally.", ("dependencies are explicit", "tests can substitute collaborators")),
            ("inversion_of_control", "object-oriented design", "A framework or container controls object creation and lifecycle.", ("centralizes wiring", "lifecycle is explicit", "frameworks", "dependency containers")),
            ("design_by_contract", "object-oriented design", "Making preconditions, postconditions, and invariants explicit.", ("invalid states fail at boundaries", "contracts need stable semantics")),
            ("cohesion", "design principles", "How strongly the responsibilities of a module belong together.", ("high cohesion localizes change", "unrelated behavior should be split")),
            ("coupling", "design principles", "How strongly one component depends on another component's details.", ("low coupling enables substitution", "shared implementation details increase change cost")),
        ]
        return [
            FundamentalConcept(concept_id, category, definition, principles)
            for concept_id, category, definition, principles in definitions
        ]
