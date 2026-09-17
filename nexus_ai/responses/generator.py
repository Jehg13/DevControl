"""Deterministic, evidence-aware response rendering."""

from .models import ResponseInput, ResponseResult
from nexus_ai.grounding import GroundingValidator


class NexusResponseGenerator:
    """Renders supplied facts and states without claiming unverified execution."""

    def generate(self, data: ResponseInput) -> ResponseResult:
        grounding = data.grounding or GroundingValidator().validate(
            data.interpretation,
            data.reasoning,
            data.tool_results,
        ).to_dict()
        sections = {
            "known": self._known(data, grounding),
            "inferences": self._typed(data.reasoning, "inference") if grounding["grounded"] else [],
            "proposals": [self._step(item) for item in data.plan],
            "pending": self._pending(data),
            "executed": self._executed(data),
            "failed": self._failed(data),
            "missing": self._missing(data, grounding),
        }
        status = self._status(sections, data)
        text = self._render(sections, status)
        history = list(data.conversation)
        history.extend([{"role": "user", "content": data.message}, {"role": "assistant", "content": text}])
        return ResponseResult(text, status, sections, history, bool(sections["known"] or sections["executed"]), False)

    def _known(self, data, grounding):
        if data.interpretation.get("action") == "query" and not grounding["grounded"]:
            return []
        values = [
            finding["statement"] for finding in data.reasoning.get("findings", [])
            if finding.get("finding_type") == "fact"
        ]
        values.extend(str(item.get("statement", item)) for item in data.knowledge if item.get("type") == "fact")
        if data.tool_results:
            for result in data.tool_results:
                if result.get("ok") is True and result.get("data") is not None:
                    values.append(self._data_summary(result))
        if grounding.get("absence"):
            values.append("No se encontraron registros que coincidan con los filtros consultados.")
        return values

    def _data_summary(self, result):
        records = result.get("data")
        entity = result.get("meta", {}).get("entity", "registros")
        if not isinstance(records, list):
            return f"Datos reales de DevControl ({entity}): {records}"
        count = len(records)
        names = [
            str(record.get("nombre") or record.get("titulo") or record.get("name"))
            for record in records
            if isinstance(record, dict) and (record.get("nombre") or record.get("titulo") or record.get("name"))
        ]
        suffix = f": {', '.join(names)}" if names else ""
        label = {
            "proyecto": "proyectos",
            "tarea": "tareas",
            "bug": "bugs",
            "incidente": "incidentes",
            "actualizacion": "actualizaciones",
        }.get(entity, "registros")
        return f"Datos reales de DevControl ({entity}): {count} {label}{suffix}."

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
            for result in data.tool_results
            if result.get("ok") is True
            and not (isinstance(result.get("meta"), dict) and result["meta"].get("entity"))
        ]

    def _failed(self, data):
        return [
            str((result.get("error") or {}).get("message", "La herramienta falló."))
            for result in data.tool_results if result.get("ok") is False
        ]

    def _missing(self, data, grounding):
        values = list(data.reasoning.get("information_needed", []))
        values.extend(grounding.get("missing", []))
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
        if sections["known"]:
            lines.append("Resumen de datos:")
            lines.append(self._summary(sections["known"]))
        if status == "action_executed":
            lines.append("La ejecución se considera realizada únicamente porque Laravel devolvió un resultado exitoso.")
        elif status in {"action_pending", "action_failed"}:
            lines.append("No indicaré que la acción se completó sin un resultado exitoso de Laravel.")
        return "\n".join(lines)

    def _summary(self, values):
        data_lines = [value for value in values if value.startswith("Datos reales de DevControl:")]
        if not data_lines:
            return "La respuesta se basa en la evidencia recibida."
        return "La consulta fue respondida con datos reales devueltos por Laravel."
