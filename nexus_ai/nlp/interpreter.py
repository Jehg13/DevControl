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

    def interpret(self, text: str) -> NlpInterpretation:
        if not isinstance(text, str) or not text.strip():
            raise ValueError("text must be a non-empty string")
        normalized = self.normalizer.normalize(text)
        tokens = self.tokenizer.tokenize(normalized)
        entity = self._entity(normalized)
        action = self._action(normalized)
        entities = self._entities(text, normalized, entity)
        filters = self._filters(normalized)
        temporal = self._temporal_references(normalized)
        contextual = self._contextual_references(normalized, entity)
        context_required = bool(contextual)
        incomplete = self._is_incomplete(normalized, action, entity)
        ambiguous = self._is_ambiguous(normalized, entity, action, context_required)
        clarification = incomplete or ambiguous
        intent = self._intent(entity, action, normalized, clarification)
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
        )

    def _entity(self, text: str) -> str | None:
        candidates = [
            entity for entity, aliases in self._ENTITY_ALIASES.items()
            if any(re.search(rf"\b{re.escape(alias)}\b", text) for alias in aliases)
        ]
        return candidates[0] if len(candidates) == 1 else (candidates[0] if candidates else None)

    def _action(self, text: str) -> str:
        if re.search(r"\b(elimina|eliminar|borra|borrar|quita|quitar)\b", text):
            return "delete"
        if re.search(r"\b(crea|crear|registra|registrar|agrega|agregar)\b", text):
            return "create"
        if re.search(r"\b(cambia|cambiar|actualiza|actualizar|edita|editar|modifica|modificar|marca)\b", text):
            return "update"
        if re.search(r"\b(analiza|analizar|revisa|revisar|diagnostica|diagnosticar)\b", text):
            return "analyze"
        if re.search(r"\b(muestra|muéstrame|muestrame|enséñame|ensename|consulta|consultar|qué|que|cómo|como)\b", text):
            return "query"
        return "unknown"

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
        if entity and re.search(r"\b(proyecto|proyectos)\s+([a-z0-9_-]+)", text):
            values["project_id"] = re.search(r"\b(proyecto|proyectos)\s+([a-z0-9_-]+)", text).group(2)
        return values

    def _filters(self, text: str) -> dict[str, str]:
        filters: dict[str, str] = {}
        for alias, value in self._PRIORITIES.items():
            if re.search(rf"\b{re.escape(alias)}\b", text):
                filters["priority"] = value
                break
        for alias, value in self._STATUSES.items():
            if alias in text:
                filters["status"] = value
                break
        return filters

    def _temporal_references(self, text: str) -> list[dict[str, str]]:
        references = []
        for phrase, normalized in (
            ("hoy", "today"), ("ayer", "yesterday"), ("esta semana", "this_week"),
            ("este mes", "this_month"), ("recientemente", "recent"),
        ):
            if phrase in text:
                references.append({"text": phrase, "normalized": normalized})
        return references

    def _contextual_references(self, text: str, entity: str | None) -> list[dict[str, Any]]:
        references = []
        for phrase, kind in self._CONTEXT_REFERENCES.items():
            if phrase in text:
                references.append({"text": phrase, "kind": kind, "entity": entity})
        return references

    def _is_incomplete(self, text: str, action: str, entity: str | None) -> bool:
        return action in {"create", "update", "delete"} and entity is None

    def _is_ambiguous(self, text: str, entity: str | None, action: str, context_required: bool) -> bool:
        if context_required and not re.search(r"\b(?:bug|tarea|incidente|actualizacion|actualización|proyecto)\b", text):
            return True
        if context_required and not re.search(r"\b(?:BUG|INC)-?\d+\b|\b\d+\b", text, re.IGNORECASE):
            return True
        if action == "delete" and not re.search(r"\b(?:bug|tarea|incidente|actualizacion|actualización)\b", text):
            return True
        return False

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
