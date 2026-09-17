"""Execution-free Spanish NLP interpreter for the DevControl domain."""

import re
from typing import Any

from .models import NlpInterpretation
from .normalizer import TextNormalizer
from .tokenizer import Tokenizer


class NexusNlpInterpreter:
    _ENTITY_ALIASES = {
        "proyecto": ("proyecto", "proyectos"),
        "tarea": ("tarea", "tareas"),
        "bug": ("bug", "bugs", "error", "errores", "problema", "problemas"),
        "incidente": ("incidente", "incidentes"),
        "actualizacion": ("actualizacion", "actualizaciones", "cambio", "cambios"),
        "usuario": ("usuario", "usuarios", "miembro", "miembros"),
        "codigo": ("codigo", "código", "archivo", "archivos", "controlador", "vista"),
    }
    _PRIORITIES = {
        "alta": "Alta", "media": "Media", "baja": "Baja",
        "critica": "Alta", "críticas": "Alta", "criticas": "Alta",
        "crítica": "Alta", "critico": "Alta", "crítico": "Alta",
        "críticos": "Alta", "criticos": "Alta",
    }
    _STATUSES = {
        "abierto": "Abierto", "abiertos": "Abierto", "pendiente": "Pendiente",
        "pendientes": "Pendiente", "reportado": "Reportado", "reportados": "Reportado",
        "investigando": "Investigando", "en progreso": "En progreso",
        "en desarrollo": "En desarrollo", "en pruebas": "En pruebas",
        "solucionado": "Solucionado", "solucionados": "Solucionado",
        "cerrado": "Cerrado", "completado": "Completado", "resuelto": "Resuelto",
        "en investigación": "En investigación", "en resolucion": "En resolución",
        "en resolución": "En resolución",
    }
    _CONTEXT_REFERENCES = {
        "ese": "singular_demonstrative",
        "esa": "singular_demonstrative",
        "eso": "unspecified_previous",
        "este": "singular_demonstrative",
        "esta": "singular_demonstrative",
        "lo anterior": "previous_result",
        "el anterior": "previous_result",
        "la anterior": "previous_result",
    }

    def __init__(self, normalizer: TextNormalizer | None = None, tokenizer: Tokenizer | None = None):
        self.normalizer = normalizer or TextNormalizer()
        self.tokenizer = tokenizer or Tokenizer()

    def interpret(self, text: str, context: dict[str, Any] | None = None) -> NlpInterpretation:
        if not isinstance(text, str) or not text.strip():
            raise ValueError("text must be a non-empty string")
        normalized = self.normalizer.normalize(text)
        matching = self.normalizer.without_accents(normalized)
        tokens = self.tokenizer.tokenize(normalized)
        context = context if isinstance(context, dict) else {}
        explicit_entity = self._entity(matching)
        inherited_entity = self._inherited_entity(context, matching)
        entity = inherited_entity if (
            inherited_entity
            and re.search(r"\bpertenecen?\s+al\s+proyecto\b", matching)
        ) else (explicit_entity or inherited_entity)
        action = self._action(matching)
        entities = self._entities(text, normalized, entity)
        filters = self._filters(matching)
        inherited = self._inherited_values(context)
        if not filters and inherited.get("filters") and self._is_contextual_follow_up(matching):
            filters.update(inherited["filters"])
        temporal = self._temporal_references(matching)
        contextual = self._contextual_references(matching, entity)
        context_required = bool(contextual) or self._is_contextual_follow_up(matching)
        incomplete = self._is_incomplete(normalized, action, entity)
        ambiguous = self._is_ambiguous(normalized, entity, action, context_required, context)
        clarification = incomplete or ambiguous
        intent = self._intent(entity, action, normalized, clarification)
        operation = self._operation(matching, action)
        sort = self._sort(matching)
        context_requirements = ["current_devcontrol_data"] if action == "query" else []
        if context_required:
            context_requirements.append("conversation_context")
        related_entities = self._related_entities(matching, entity)
        negations = self._negations(matching)
        constraints = self._constraints(matching)
        group_by = self._group_by(matching)
        relations = list(related_entities.keys())
        inherited_context = inherited if context_required else {}
        return NlpInterpretation(
            original_text=text,
            normalized_text=normalized,
            tokens=tokens,
            intent=intent,
            entity=entity,
            action=action,
            entities=entities,
            filters=filters,
            temporal_references=temporal,
            contextual_references=contextual,
            ambiguous=ambiguous,
            incomplete=incomplete,
            requires_context=context_required,
            requires_clarification=clarification,
            requires_confirmation=action == "delete",
            executable=False,
            confidence=self._confidence(intent, clarification),
            clarification_question=self._question(entity, action) if clarification else None,
            operation=operation,
            sort=sort,
            limit=self._limit(matching),
            context_requirements=context_requirements,
            related_entities=related_entities,
            negations=negations,
            constraints=constraints,
            inherited_context=inherited_context,
            ambiguity_level="high" if ambiguous else ("medium" if context_required else "none"),
            group_by=group_by,
            relations=relations,
        )

    def _inherited_entity(self, context: dict[str, Any], text: str) -> str | None:
        if not self._is_contextual_follow_up(text):
            return None
        inherited = self._inherited_values(context)
        value = inherited.get("entity")
        return value if isinstance(value, str) else None

    def _inherited_values(self, context: dict[str, Any]) -> dict[str, Any]:
        turns = context.get("recent_turns", [])
        for turn in reversed(turns if isinstance(turns, list) else []):
            if not isinstance(turn, dict):
                continue
            interpretation = turn.get("interpretation")
            if isinstance(interpretation, dict) and (
                interpretation.get("entity") or interpretation.get("filters")
            ):
                return {
                    "entity": interpretation.get("entity"),
                    "filters": interpretation.get("filters", {}),
                    "project": interpretation.get("entities", {}).get("project")
                    if isinstance(interpretation.get("entities"), dict) else None,
                }
        return {}

    def _group_by(self, text: str) -> list[str]:
        if re.search(r"\bpor proyecto\b", text):
            return ["project_id"]
        if re.search(r"\bpor estado\b", text):
            return ["status"]
        if re.search(r"\bpor prioridad\b", text):
            return ["priority"]
        return []

    def _is_contextual_follow_up(self, text: str) -> bool:
        if re.search(r"\b(?:ese|esa|ellos|ellas|anterior|primero)\b", text):
            return True
        if re.match(r"^\s*y\b", text):
            return True
        has_entity = any(
            re.search(rf"\b{re.escape(alias)}\b", text)
            for aliases in self._ENTITY_ALIASES.values()
            for alias in aliases
        )
        return not has_entity and bool(re.search(
            r"\b(?:cu[aá]les?|pendientes?|cr[ií]ticos?)\b|"
            r"\b(?:tiene|tienen)\s+(?:m[aá]s|menos)\b",
            text,
        ))

    def _entity(self, text: str) -> str | None:
        candidates = []
        for entity, aliases in self._ENTITY_ALIASES.items():
            positions = [
                match.start()
                for alias in aliases
                for match in re.finditer(rf"\b{re.escape(alias)}\b", text)
            ]
            if positions:
                candidates.append((min(positions), entity))
        return min(candidates)[1] if candidates else None

    def _action(self, text: str) -> str:
        if re.search(r"\b(elimina|eliminar|borra|borrar|quita|quitar)\b", text):
            return "delete"
        if re.search(r"\b(crea|crear|registra|registrar|agrega|agregar)\b", text):
            return "create"
        if re.search(r"\b(cambia|cambiar|actualiza|actualizar|edita|editar|modifica|modificar|marca)\b", text):
            return "update"
        if re.search(r"\b(analiza|analizar|revisa|revisar|diagnostica|diagnosticar)\b", text):
            return "analyze"
        if re.search(
            r"\b(muestra|muéstrame|muestrame|enséñame|ensename|lista|listar|"
            r"consulta|consultar|qué|que|cómo|como|cuantos|cuantas|cual|cuales|"
            r"agrupa|agrupar|estadística|estadisticas|estadística|resumen)\b",
            text,
        ):
            return "query"
        return "unknown"

    def _operation(self, text: str, action: str) -> str:
        if action != "query":
            return action
        if re.search(r"\b(cu[aá]ntos|cu[aá]ntas|n[uú]mero de|total)\b", text):
            return "count"
        if re.search(
            r"\b(compara|comparar|cu[aá]l tiene m[aá]s|cu[aá]l proyecto tiene|qu[eé] proyecto tiene)\b",
            text,
        ):
            return "compare"
        if re.search(r"\b(estad[ií]stica|estad[ií]sticas|resumen)\b", text):
            return "statistics"
        if re.search(r"\b(agrupa|agrupados|por estado|por prioridad|por proyecto)\b", text):
            return "group"
        if re.search(r"\b(detalle|detalles|informaci[oó]n de|sobre el proyecto)\b", text):
            return "detail"
        if re.search(r"\b(busca|buscar|encuentra|encontrar)\b", text):
            return "search"
        return "list"

    def _sort(self, text: str) -> dict[str, str]:
        if re.search(r"\b(m[aá]s bugs|mayor n[uú]mero de bugs)\b", text):
            return {"field": "bugs_count", "direction": "desc"}
        if re.search(r"\b(m[aá]s tareas pendientes|mayor n[uú]mero de tareas)\b", text):
            return {"field": "pending_tasks_count", "direction": "desc"}
        if re.search(r"\b(revisarse primero|primero)\b", text):
            return {"field": "priority", "direction": "desc"}
        return {}

    def _limit(self, text: str) -> int | None:
        match = re.search(r"\b(?:primer[oa]s?|top)\s+(\d+)\b", text)
        return int(match.group(1)) if match else None

    def _intent(self, entity: str | None, action: str, text: str, clarification: bool) -> str:
        if clarification and action == "unknown":
            return "ambiguo"
        if clarification and entity is None:
            return "solicitar_aclaracion"
        if action == "follow_up":
            return "seguimiento_conversacion"
        if action == "analyze":
            return "analizar_codigo" if entity == "codigo" else "analizar_problema"
        if entity is None:
            return "solicitar_aclaracion" if clarification else "general"
        return f"{action}_{entity}"

    def _entities(self, original_text: str, text: str, entity: str | None) -> dict[str, Any]:
        values: dict[str, Any] = {}
        identifier = re.search(r"\b((?:bug|inc)-\d+|\d+)\b", text)
        if identifier:
            values["id"] = identifier.group(1).upper()
        path = re.search(r"\b((?:app|resources|routes|config|database)/[\w./-]+\.php)\b", original_text, re.IGNORECASE)
        if path:
            values["path"] = path.group(1)
        project_id = re.search(r"\bproyecto\s+(\d+)\b", text)
        if project_id:
            values["project_id"] = project_id.group(1)
        project_name = re.search(
            r"\b(?:proyecto|project)\s+([a-z][\w-]*)\b",
            text,
            re.IGNORECASE,
        )
        if project_name and project_name.group(1).casefold() not in {
            "tiene", "tienen", "con", "más", "mas", "anterior", "previo", "previa",
        }:
            values["project"] = project_name.group(1)
        user = re.search(r"\b(?:usuario|miembro)\s+([a-z][\w-]*)\b", text, re.IGNORECASE)
        if user:
            values["user"] = user.group(1)
        quantity = re.search(
            r"(?<![/-])\b(?:exactamente|al menos|m[ií]nimo|m[aá]ximo)?\s*(\d+)\b(?![/-])",
            text,
        )
        if quantity:
            values["quantity"] = int(quantity.group(1))
        if re.search(r"\b(?:m[aá]s|mayor|menos|menor|igual)\b", text):
            values["comparison"] = True
        return values

    def _filters(self, text: str) -> dict[str, str]:
        text = self.normalizer.without_accents(text)
        filters: dict[str, str] = {}
        for alias, value in self._PRIORITIES.items():
            if re.search(rf"\b{re.escape(alias)}\b", text):
                filters["priority"] = value
                break
        for alias, value in self._STATUSES.items():
            if re.search(rf"\b{re.escape(alias)}\b", text):
                filters["status"] = value
                break
        project = re.search(r"\b(?:en|de|del)\s+(?:el\s+)?proyecto\s+([a-z0-9_-]+)", text)
        if project:
            filters["project_name"] = project.group(1)
        elif re.search(r"\bdevcontrol\b", text):
            filters["project_name"] = "DevControl"
        return filters

    def _temporal_references(self, text: str) -> list[dict[str, str]]:
        references = []
        for phrase, normalized in (
            ("hoy", "today"), ("ayer", "yesterday"), ("esta semana", "this_week"),
            ("este mes", "this_month"), ("recientemente", "recent"),
        ):
            if re.search(rf"\b{re.escape(phrase)}\b", text):
                references.append({"text": phrase, "normalized": normalized})
        for match in re.finditer(r"\b(\d{1,2}[/-]\d{1,2}[/-]\d{2,4})\b", text):
            references.append({"text": match.group(1), "normalized": match.group(1)})
        if re.search(r"\b(?:anterior|previo|previa)\b", text):
            references.append({"text": "anterior", "normalized": "previous"})
        return references

    def _contextual_references(self, text: str, entity: str | None) -> list[dict[str, Any]]:
        references = []
        for phrase, kind in self._CONTEXT_REFERENCES.items():
            if re.search(rf"\b{re.escape(phrase)}\b", text):
                references.append({"text": phrase, "kind": kind, "entity": entity})
        return references

    def _is_incomplete(self, text: str, action: str, entity: str | None) -> bool:
        return action in {"create", "update", "delete"} and entity is None

    def _is_ambiguous(
        self,
        text: str,
        entity: str | None,
        action: str,
        context_required: bool,
        context: dict[str, Any],
    ) -> bool:
        if context_required and entity is None and not self._inherited_values(context):
            return True
        if context_required and action in {"delete", "update"} and not re.search(
            r"\b(?:BUG|INC)-?\d+\b|\b\d+\b", text, re.IGNORECASE
        ):
            return True
        if action == "delete" and not re.search(r"\b(?:bug|tarea|incidente|actualizacion|actualización)\b", text):
            return True
        return False

    def _related_entities(self, text: str, entity: str | None) -> dict[str, list[str]]:
        related = {}
        for candidate, aliases in self._ENTITY_ALIASES.items():
            if candidate == entity:
                continue
            matches = [alias for alias in aliases if re.search(rf"\b{re.escape(alias)}\b", text)]
            if matches:
                related[candidate] = matches
        return related

    def _negations(self, text: str) -> list[str]:
        return re.findall(
            r"\b(?:no|nunca|jam[aá]s|todav[ií]a no|sin)\b(?:\s+\w+){0,3}",
            text,
        )

    def _constraints(self, text: str) -> dict[str, Any]:
        constraints: dict[str, Any] = {}
        if re.search(r"\b(?:solo|s[oó]lo|únicamente|unicamente)\b", text):
            constraints["exclusive"] = True
        if re.search(r"\b(?:al menos|m[ií]nimo)\b", text):
            constraints["minimum"] = True
        if re.search(r"\b(?:m[aá]ximo|hasta)\b", text):
            constraints["maximum"] = True
        if re.search(r"\b(?:antes de|despu[eé]s de|entre)\b", text):
            constraints["date_range"] = True
        return constraints

    def _confidence(self, intent: str, clarification: bool) -> str:
        if clarification:
            return "low"
        if intent in {"general", "solicitar_aclaracion"}:
            return "low"
        return "medium"

    def _question(self, entity: str | None, action: str) -> str:
        if entity is None:
            return "¿Qué entidad de DevControl deseas indicar?"
        if action == "delete":
            return f"¿Qué {entity} específico deseas eliminar?"
        return f"¿Qué información falta para {action} el {entity}?"
