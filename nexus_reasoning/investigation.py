"""Read-only technical investigation planning and evidence analysis."""

from __future__ import annotations

from dataclasses import asdict, dataclass, field
from datetime import datetime, timezone
from typing import Any


@dataclass(frozen=True)
class InvestigationEvidence:
    origin: str
    tool: str | None
    query: dict[str, Any]
    result: Any
    timestamp: str
    relevance: float

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class InvestigationResult:
    problem: str
    objective: str | None
    questions: tuple[str, ...]
    required_evidence: tuple[str, ...]
    required_tools: tuple[str, ...]
    evidence: tuple[InvestigationEvidence, ...]
    hypotheses: tuple[dict[str, Any], ...]
    validation: tuple[dict[str, Any], ...]
    conclusion: dict[str, Any]
    missing_information: tuple[str, ...]
    status: str
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["evidence"] = [item.to_dict() for item in self.evidence]
        return result


class TechnicalInvestigationEngine:
    """Derives an investigation from the problem; it never invokes tools."""

    _MARKERS = (
        "error", "falla", "fallo", "bug", "incidente", "problema",
        "excepción", "exception", "lento", "timeout", "no funciona",
    )

    def build(
        self,
        problem: str,
        *,
        tools: list[dict[str, Any]] | None = None,
        evidence: list[dict[str, Any]] | None = None,
    ) -> InvestigationResult:
        if not isinstance(problem, str) or not problem.strip():
            raise ValueError("problem must be a non-empty string")
        text = problem.strip()
        lowered = text.casefold()
        if len(lowered.split()) < 3 or not any(marker in lowered for marker in self._MARKERS):
            return InvestigationResult(
                text, None, (), (), (), (), (), (),
                {"status": "needs_clarification", "confidence": 0.0},
                ("technical_objective",), "needs_clarification",
            )

        required = self._required_evidence(lowered)
        normalized = tuple(self._normalize_evidence(item) for item in (evidence or []) if isinstance(item, dict))
        usable = tuple(item for item in normalized if item is not None)
        missing = tuple(
            kind for kind in required
            if not any(item.query.get("evidence_type") == kind for item in usable)
        )
        hypotheses = self._hypotheses(usable)
        validation = tuple({
            "hypothesis_id": hypothesis["id"],
            "status": "supported" if usable and not missing else "pending",
            "evidence_count": len(usable),
        } for hypothesis in hypotheses)
        if not usable:
            status = "planned"
            conclusion = {
                "status": "pending_evidence",
                "statement": "La investigación está definida, pero aún no tiene resultados.",
                "confidence": 0.0,
            }
        elif missing:
            status = "incomplete"
            conclusion = {
                "status": "insufficient_evidence",
                "statement": "La evidencia recibida no cubre todas las preguntas necesarias.",
                "confidence": 0.0,
            }
        else:
            status = "analyzed"
            confidence = min((item.relevance for item in usable), default=0.0)
            conclusion = {
                "status": "supported",
                "statement": "La hipótesis activa está respaldada por la evidencia recibida.",
                "confidence": confidence,
            }
        return InvestigationResult(
            text,
            f"Determinar la causa, alcance e impacto del problema descrito: {text}",
            self._questions(),
            required,
            self._required_tools(tools or [], lowered),
            usable,
            hypotheses,
            validation,
            conclusion,
            missing,
            status,
        )

    @staticmethod
    def _questions() -> tuple[str, ...]:
        return (
            "¿Qué comportamiento observable está fallando?",
            "¿Qué evidencia reproduce o confirma el problema?",
            "¿Qué componentes o entidades están relacionados?",
            "¿Qué hipótesis explica mejor la evidencia disponible?",
        )

    @staticmethod
    def _required_evidence(text: str) -> tuple[str, ...]:
        required = ["observed_behavior", "reproduction_or_event"]
        if any(term in text for term in ("error", "bug", "excepción", "exception", "falla")):
            required += ["error_record"]
        if any(term in text for term in ("lento", "timeout", "rendimiento")):
            required += ["performance_measurement"]
        if any(term in text for term in ("permiso", "acceso", "autorización", "login")):
            required += ["authorization_result"]
        return tuple(required)

    @staticmethod
    def _required_tools(tools: list[dict[str, Any]], text: str) -> tuple[str, ...]:
        terms = {word for word in text.split() if len(word) > 3}
        names = []
        for tool in tools:
            name = tool.get("name")
            searchable = f"{name or ''} {tool.get('description', '')}".casefold()
            if isinstance(name, str) and any(term in searchable for term in terms):
                names.append(name)
        return tuple(dict.fromkeys(names))

    @staticmethod
    def _normalize_evidence(item: dict[str, Any]) -> InvestigationEvidence | None:
        query = item.get("query")
        if not isinstance(query, dict) or not isinstance(query.get("evidence_type"), str):
            return None
        timestamp = item.get("timestamp") or datetime.now(timezone.utc).isoformat()
        return InvestigationEvidence(
            origin=str(item.get("origin", "unknown")),
            tool=item.get("tool") if isinstance(item.get("tool"), str) else None,
            query=query,
            result=item.get("result"),
            timestamp=str(timestamp),
            relevance=max(0.0, min(1.0, float(item.get("relevance", 1.0)))),
        )

    @staticmethod
    def _hypotheses(evidence: tuple[InvestigationEvidence, ...]) -> tuple[dict[str, Any], ...]:
        if not evidence:
            return ()
        return ({
            "id": "hypothesis-1",
            "statement": "El comportamiento observado tiene una causa verificable en los resultados recuperados.",
            "evidence": [item.query for item in evidence],
            "confidence": min(item.relevance for item in evidence),
            "status": "active",
        },)
