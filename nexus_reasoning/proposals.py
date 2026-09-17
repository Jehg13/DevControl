"""Evidence-derived, read-only technical solution proposals."""

from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Any


@dataclass(frozen=True)
class SolutionProposal:
    proposal_id: str
    problem: str
    cause: str
    solution: str
    affected_components: tuple[dict[str, Any], ...]
    steps: tuple[str, ...]
    dependencies: tuple[str, ...]
    risks: tuple[str, ...]
    required_tests: tuple[str, ...]
    expected_result: str
    confidence: float
    evidence: tuple[Any, ...]
    status: str = "proposed"
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["affected_components"] = list(self.affected_components)
        result["steps"] = list(self.steps)
        result["dependencies"] = list(self.dependencies)
        result["risks"] = list(self.risks)
        result["required_tests"] = list(self.required_tests)
        result["evidence"] = list(self.evidence)
        return result


@dataclass(frozen=True)
class SolutionProposalResult:
    problem: str
    proposals: tuple[SolutionProposal, ...]
    missing_information: tuple[str, ...]
    status: str
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        return {
            "problem": self.problem,
            "proposals": [proposal.to_dict() for proposal in self.proposals],
            "missing_information": list(self.missing_information),
            "status": self.status,
            "executable": self.executable,
        }


class SolutionProposalEngine:
    """Creates alternatives only from a validated diagnostic and observed impact."""

    _SOLUTIONS = {
        "database": (
            (
                "database-migration",
                "Sincronizar la migración o el esquema que falta antes de volver a consultar.",
                ("Validar el esquema en un entorno seguro.", "Comparar migraciones aplicadas.", "Preparar la migración pendiente para revisión."),
                ("Acceso al esquema y al historial de migraciones.",),
                ("La migración puede ser incompatible con datos existentes.",),
                ("Prueba de migración en una base aislada.", "Prueba de regresión de la consulta."),
            ),
            (
                "database-query-guard",
                "Ajustar la consulta para detectar explícitamente el esquema ausente y degradar de forma segura.",
                ("Revisar la consulta en el componente afectado.", "Definir el comportamiento cuando la tabla no exista.", "Agregar una prueba de error controlado."),
                ("Contrato de datos estable.",),
                ("Ocultar un problema de despliegue si se usa como sustituto de la migración.",),
                ("Prueba de consulta válida.", "Prueba de esquema ausente."),
            ),
        ),
        "dependency": (
            (
                "dependency-alignment",
                "Alinear la dependencia y el entorno con el contrato observado.",
                ("Identificar la versión requerida.", "Comparar lockfiles y entorno.", "Validar la resolución en un entorno aislado."),
                ("Lockfile y versión del runtime.",),
                ("Actualizar una dependencia puede introducir incompatibilidades.",),
                ("Prueba de instalación reproducible.", "Pruebas del componente afectado."),
            ),
        ),
        "configuration": (
            (
                "configuration-correction",
                "Corregir la configuración no secreta que impide la conexión o ejecución.",
                ("Comparar configuración efectiva con un entorno conocido.", "Identificar la clave faltante sin exponer secretos.", "Validar el arranque en entorno seguro."),
                ("Acceso a configuración redactada.",),
                ("Modificar configuración puede afectar otros entornos.",),
                ("Prueba de arranque.", "Prueba de conectividad controlada."),
            ),
        ),
        "null_reference": (
            (
                "initialization-guard",
                "Asegurar la inicialización y validar el valor antes de usarlo.",
                ("Localizar el símbolo en el stack trace.", "Revisar el contrato del valor.", "Diseñar una ruta explícita para el valor ausente."),
                ("Código y contrato del componente.",),
                ("Un valor por defecto puede ocultar un error de negocio.",),
                ("Prueba del valor válido.", "Prueba del valor ausente."),
            ),
        ),
    }

    def propose(
        self,
        diagnosis: Any,
        *,
        impact: Any | None = None,
    ) -> SolutionProposalResult:
        status = getattr(diagnosis, "status", None)
        confidence = float(getattr(diagnosis, "confidence", 0.0))
        problem = self._problem(diagnosis)
        if status != "supported" or confidence < 0.45:
            missing = tuple(getattr(diagnosis, "missing_information", ()) or ())
            return SolutionProposalResult(
                problem,
                (),
                missing or ("diagnosis validada con evidencia suficiente",),
                "insufficient_evidence",
            )

        causes = self._causes(diagnosis)
        if not causes:
            return SolutionProposalResult(
                problem, (), ("causa probable identificada",), "insufficient_evidence"
            )
        affected = self._affected(impact)
        evidence = tuple(getattr(diagnosis, "evidence", ()) or ())
        proposals = []
        for cause_index, cause in enumerate(causes):
            definitions = self._SOLUTIONS.get(cause, (self._generic(cause),))
            for definition_index, definition in enumerate(definitions):
                identifier, solution, steps, dependencies, risks, tests = definition
                proposals.append(SolutionProposal(
                    f"{identifier}-{cause_index + 1}-{definition_index + 1}",
                    problem,
                    cause,
                    solution,
                    affected,
                    steps,
                    dependencies,
                    risks,
                    tests,
                    self._expected(cause),
                    round(max(0.0, min(1.0, confidence - cause_index * 0.12)), 4),
                    evidence + self._impact_evidence(affected),
                ))
        return SolutionProposalResult(problem, tuple(proposals), (), "proposed")

    @staticmethod
    def _problem(diagnosis: Any) -> str:
        return str(getattr(diagnosis, "symptoms", ())[:1] or ("Problema técnico diagnosticado.",))[0]

    @staticmethod
    def _causes(diagnosis: Any) -> tuple[str, ...]:
        hypotheses = getattr(diagnosis, "hypotheses", ()) or ()
        causes = tuple(
            str(item.get("cause"))
            for item in hypotheses
            if isinstance(item, dict) and item.get("cause")
        )
        if causes:
            return causes
        probable = getattr(diagnosis, "probable_causes", ()) or ()
        return tuple(item.split(": ", 1)[-1].rstrip(".") for item in probable if item)

    @staticmethod
    def _affected(impact: Any | None) -> tuple[dict[str, Any], ...]:
        if impact is None:
            return ()
        components = getattr(impact, "components", None)
        if components is None and isinstance(impact, dict):
            components = impact.get("components", [])
        output = []
        for item in components or ():
            value = item.to_dict() if hasattr(item, "to_dict") else item
            if isinstance(value, dict) and value.get("component"):
                output.append(value)
        return tuple(output)

    @staticmethod
    def _impact_evidence(affected: tuple[dict[str, Any], ...]) -> tuple[Any, ...]:
        return tuple(item.get("evidence", []) for item in affected if item.get("evidence"))

    @staticmethod
    def _expected(cause: str) -> str:
        return f"El componente relacionado con {cause} funciona correctamente y las pruebas confirman la regresión corregida."

    @staticmethod
    def _generic(cause: str) -> tuple[Any, ...]:
        return (
            "generic-isolation",
            f"Aislar y corregir de forma controlada la causa probable de tipo {cause}.",
            ("Reproducir el problema.", "Revisar la evidencia en el componente afectado.", "Validar una corrección mínima."),
            ("Reproducción verificable.",),
            ("La causa puede no estar confirmada y la corrección podría ser insuficiente.",),
            ("Prueba de reproducción.", "Prueba de regresión."),
        )
