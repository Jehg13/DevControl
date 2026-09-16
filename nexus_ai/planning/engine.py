"""Deterministic planner; it proposes steps but never invokes tools."""

from .models import PlanRequest, PlanResult, PlanStep


class NexusPlanner:
    _DEPLOY_TEMPLATE = (
        ("Revisar el proyecto", ["project"], "project.inspect", "proyecto revisado", "bajo", None, False),
        ("Revisar la configuración", ["configuration"], "configuration.inspect", "configuración evaluada", "medio", "nexus.read", False),
        ("Revisar las dependencias", ["dependencies"], "dependencies.inspect", "dependencias evaluadas", "medio", "nexus.read", False),
        ("Revisar las pruebas", ["test_results"], "tests.run", "resultado de pruebas disponible", "medio", "nexus.read", False),
        ("Revisar el repositorio", ["repository"], "repository.inspect", "estado del repositorio evaluado", "medio", "nexus.read", False),
        ("Identificar problemas", ["review_results"], "project.analyze", "problemas clasificados", "medio", "nexus.read", False),
        ("Generar checklist de despliegue", ["analysis"], "checklist.generate", "checklist estructurado", "bajo", None, False),
    )

    def create_plan(self, request: PlanRequest) -> PlanResult:
        text = request.objective.casefold()
        missing: list[str] = []
        if any(word in text for word in ("desplegar", "despliegue", "deploy", "publicar")):
            definitions = self._DEPLOY_TEMPLATE
        elif any(word in text for word in ("revisar", "revisa", "analiza", "analizar", "evaluar")):
            definitions = (("Analizar el objetivo solicitado", ["project"], "project.analyze", "análisis estructurado", "medio", "nexus.read", False),)
        else:
            missing.append("Se necesita un objetivo operativo más específico.")
            return PlanResult(request.objective, [], missing, "insufficient_data")

        steps: list[PlanStep] = []
        for number, definition in enumerate(definitions, 1):
            objective, data, tool, expected, risk, permission, confirmation = definition
            depends = list(range(1, number))
            feasible = all(item in request.available_data for item in data)
            blocker = None if feasible else f"Falta información: {', '.join(data)}"
            if permission and permission not in request.permissions:
                feasible = False
                permission_blocker = f"Laravel no reportó el permiso requerido: {permission}"
                blocker = f"{blocker}; {permission_blocker}" if blocker else permission_blocker
            tool_available = not request.tools or any(item.get("name") == tool for item in request.tools)
            if not tool_available:
                feasible = False
                tool_blocker = f"La herramienta potencial no está disponible: {tool}"
                blocker = f"{blocker}; {tool_blocker}" if blocker else tool_blocker
            steps.append(PlanStep(
                number, objective, data, depends, tool, expected, risk, confirmation,
                permission, False, feasible, blocker,
            ))
        status = "blocked" if any(not step.feasible for step in steps) else "planned"
        return PlanResult(request.objective, steps, missing, status, executable=False)
