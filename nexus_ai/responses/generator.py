"""Deterministic, evidence-aware response rendering."""

from .models import ResponseInput, ResponseResult


class NexusResponseGenerator:
    """Renders supplied facts and states without claiming unverified execution."""

    def generate(self, data: ResponseInput) -> ResponseResult:
        sections = {
            "known": self._known(data),
            "inferences": self._typed(data.reasoning, "inference"),
            "proposals": [self._step(item) for item in data.plan],
            "pending": self._pending(data),
            "executed": self._executed(data),
            "failed": self._failed(data),
            "missing": self._missing(data),
        }
        status = self._status(sections, data)
        text = self._render(sections, status)
        history = list(data.conversation)
        history.extend([{"role": "user", "content": data.message}, {"role": "assistant", "content": text}])
        return ResponseResult(text, status, sections, history, bool(sections["known"] or sections["executed"]), False)

    def _known(self, data):
        values = [
            finding["statement"] for finding in data.reasoning.get("findings", [])
            if finding.get("finding_type") == "fact"
        ]
        values.extend(str(item.get("statement", item)) for item in data.knowledge if item.get("type") == "fact")
        return values

    def _typed(self, reasoning, kind):
        return [finding["statement"] for finding in reasoning.get("findings", []) if finding.get("finding_type") == kind]

    def _step(self, step):
        return f"{step.get('number', '?')}. {step.get('objective', 'Paso propuesto')}"

    def _pending(self, data):
        values = [f"Plan pendiente de revisión: {self._step(step)}" for step in data.plan]
        if data.execution_status in {"pending", "confirmation_required"}:
            values.append("La acción está pendiente de confirmación o ejecución en Laravel.")
        return values

    def _executed(self, data):
        return [
            str(result.get("data", "La herramienta informó una ejecución exitosa."))
            for result in data.tool_results if result.get("ok") is True
        ]

    def _failed(self, data):
        return [
            str((result.get("error") or {}).get("message", "La herramienta falló."))
            for result in data.tool_results if result.get("ok") is False
        ]

    def _missing(self, data):
        values = list(data.reasoning.get("information_needed", []))
        if not data.reasoning and not data.tool_results and not data.knowledge and not data.plan:
            values.append("No se recibieron datos verificables de DevControl.")
        return values

    def _status(self, sections, data):
        if sections["failed"]:
            return "action_failed"
        if sections["executed"]:
            return "action_executed"
        if sections["missing"]:
            return "missing_information"
        if data.execution_status in {"pending", "confirmation_required"}:
            return "action_pending"
        if sections["proposals"]:
            return "plan_proposed"
        if sections["pending"]:
            return "action_pending"
        return "answered" if sections["known"] or sections["inferences"] else "unresolved"

    def _render(self, sections, status):
        lines = [f"Estado: {status}."]
        labels = (
            ("known", "Información confirmada"),
            ("inferences", "Inferencias"),
            ("proposals", "Propuesta o plan"),
            ("pending", "Acción pendiente"),
            ("executed", "Acción ejecutada por Laravel"),
            ("failed", "Acción fallida"),
            ("missing", "Información faltante"),
        )
        for key, label in labels:
            if sections[key]:
                lines.append(f"{label}:")
                lines.extend(f"- {item}" for item in sections[key])
        if status == "action_executed":
            lines.append("La ejecución se considera realizada únicamente porque Laravel devolvió un resultado exitoso.")
        elif status in {"action_pending", "action_failed"}:
            lines.append("No indicaré que la acción se completó sin un resultado exitoso de Laravel.")
        return "\n".join(lines)
