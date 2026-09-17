"""Validation-first engineering plans that do not execute changes."""

from __future__ import annotations

from dataclasses import asdict, dataclass
from typing import Any


@dataclass(frozen=True)
class EngineeringStep:
    step_id: str
    description: str
    depends_on: tuple[str, ...]
    tools: tuple[str, ...]
    validations: tuple[str, ...]
    checkpoint: str

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["depends_on"] = list(self.depends_on)
        result["tools"] = list(self.tools)
        result["validations"] = list(self.validations)
        return result


@dataclass(frozen=True)
class EngineeringPlan:
    plan_id: str
    objective: str
    preconditions: tuple[str, ...]
    steps: tuple[EngineeringStep, ...]
    affected_files: tuple[str, ...]
    required_tools: tuple[str, ...]
    required_permissions: tuple[str, ...]
    tests: tuple[str, ...]
    validations: tuple[str, ...]
    checkpoints: tuple[str, ...]
    rollback: tuple[str, ...]
    expected_result: str
    status: str
    executable: bool = True
    executed: bool = False

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["preconditions"] = list(self.preconditions)
        result["steps"] = [step.to_dict() for step in self.steps]
        for key in (
            "affected_files", "required_tools", "required_permissions", "tests",
            "validations", "checkpoints", "rollback",
        ):
            result[key] = list(getattr(self, key))
        return result


@dataclass(frozen=True)
class PlanValidation:
    valid: bool
    errors: tuple[str, ...]
    warnings: tuple[str, ...] = ()

    def to_dict(self) -> dict[str, Any]:
        return asdict(self) | {
            "errors": list(self.errors),
            "warnings": list(self.warnings),
        }


class EngineeringPlanningEngine:
    """Converts a proposed solution into a dependency-aware executable plan."""

    def create(self, proposal: Any, *, plan_id: str | None = None) -> EngineeringPlan:
        if getattr(proposal, "status", None) != "proposed":
            raise ValueError("only proposed solutions can become engineering plans")
        if getattr(proposal, "executable", True):
            raise ValueError("proposal execution state is invalid")

        affected = tuple(self._component_path(item) for item in (proposal.affected_components or ()))
        affected = tuple(item for item in affected if item)
        tests = tuple(proposal.required_tests)
        steps = (
            EngineeringStep(
                "prepare",
                "Confirmar la diagnosis, la evidencia y el estado limpio del workspace.",
                (),
                ("nexus.code.inspect",),
                ("La evidencia sigue correspondiendo al componente afectado.",),
                "checkpoint-before-change",
            ),
            EngineeringStep(
                "apply",
                proposal.solution,
                ("prepare",),
                ("nexus.code.modify",),
                ("Los cambios quedan limitados a los componentes aprobados.",),
                "checkpoint-after-change",
            ),
            EngineeringStep(
                "test",
                "Ejecutar las pruebas necesarias y comprobar la regresión corregida.",
                ("apply",),
                ("nexus.code.validate",),
                tests or ("La prueba de reproducción deja de fallar.",),
                "checkpoint-after-tests",
            ),
            EngineeringStep(
                "verify",
                "Comparar el resultado con la diagnosis, el impacto y el resultado esperado.",
                ("test",),
                ("nexus.code.inspect", "nexus.code.validate"),
                ("No aparecen regresiones en los componentes relacionados.",),
                "checkpoint-before-acceptance",
            ),
        )
        permissions = {"nexus.read", "nexus.code.modify", "nexus.code.validate"}
        return EngineeringPlan(
            plan_id or f"plan-{proposal.proposal_id}",
            f"Resolver: {proposal.problem}",
            (
                "La diagnosis está validada y la propuesta tiene evidencia.",
                "El usuario autoriza explícitamente cualquier futura ejecución.",
                "El workspace está limpio y tiene un punto de restauración.",
            ),
            steps,
            affected,
            tuple(dict.fromkeys(tool for step in steps for tool in step.tools)),
            tuple(sorted(permissions)),
            tests,
            tuple(validation for step in steps for validation in step.validations),
            tuple(step.checkpoint for step in steps),
            (
                "Conservar un snapshot antes del primer cambio.",
                "Restaurar el snapshot si una prueba o validación falla.",
                "No aceptar ni publicar cambios con validación fallida.",
            ),
            proposal.expected_result,
            "ready_for_authorization",
        )

    def validate(self, plan: EngineeringPlan) -> PlanValidation:
        errors: list[str] = []
        warnings: list[str] = []
        if plan.executed:
            errors.append("un engineering plan must not be marked as executed")
        if not plan.executable:
            errors.append("plan must remain executable for a later authorized run")
        if not plan.steps:
            errors.append("plan requires steps")
        step_ids = [step.step_id for step in plan.steps]
        if len(step_ids) != len(set(step_ids)):
            errors.append("step identifiers must be unique")
        known: set[str] = set()
        for step in plan.steps:
            missing = set(step.depends_on) - known
            if missing:
                errors.append(f"{step.step_id} has unresolved dependencies: {sorted(missing)}")
            known.add(step.step_id)
            if not step.checkpoint:
                errors.append(f"{step.step_id} requires a checkpoint")
        if not plan.preconditions:
            errors.append("plan requires preconditions")
        if not plan.rollback:
            errors.append("plan requires rollback instructions")
        if not plan.required_permissions:
            errors.append("plan requires permissions")
        if not plan.validations:
            errors.append("plan requires validations")
        if plan.status != "ready_for_authorization":
            warnings.append("plan status is not ready_for_authorization")
        return PlanValidation(not errors, tuple(errors), tuple(warnings))

    @staticmethod
    def _component_path(component: Any) -> str | None:
        if not isinstance(component, dict):
            return None
        value = component.get("component")
        component_type = component.get("component_type")
        if component_type in {"file", "directory"} and isinstance(value, str):
            return value
        if isinstance(value, str) and "::" in value:
            return value.split("::", 1)[0]
        return value if isinstance(value, str) else None
