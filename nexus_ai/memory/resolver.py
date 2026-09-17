"""Session-scoped conversational reference resolution."""

import re
import unicodedata
from typing import Any

from .models import ContextReference, MemoryRecord


class ContextResolver:
    """Resolves references only from non-expired records in the current session."""

    _ENTITY_ALIASES = {
        "proyecto": ("proyecto", "proyectos"),
        "bug": ("bug", "bugs", "error", "errores"),
        "tarea": ("tarea", "tareas"),
        "incidente": ("incidente", "incidentes"),
        "actualizacion": ("actualización", "actualizacion", "actualizaciones"),
        "usuario": ("usuario", "usuarios", "miembro", "miembros"),
    }
    _PLURAL = {"ellos", "ellas", "cuáles", "cuales", "estos", "estas", "los", "las"}

    def resolve(
        self,
        text: str,
        records: list[MemoryRecord],
        entity: str | None = None,
    ) -> list[ContextReference]:
        normalized = unicodedata.normalize("NFD", text.casefold())
        normalized = "".join(char for char in normalized if unicodedata.category(char) != "Mn")
        references = []
        for phrase, kind, target_entity, strategy in self._references(normalized, entity):
            candidates = self._candidates(records, target_entity, normalized, kind)
            if strategy == "first":
                selected = candidates[0] if candidates else None
                references.append(self._result(
                    phrase, candidates, selected, selected is not None, kind, target_entity, strategy,
                ))
            elif strategy == "last":
                selected = candidates[-1] if candidates else None
                references.append(self._result(
                    phrase, candidates, selected, selected is not None, kind, target_entity, strategy,
                ))
            elif strategy == "previous" and candidates:
                selected = candidates[0] if len(candidates) == 1 else None
                references.append(self._result(
                    phrase, candidates, selected, selected is not None, kind, target_entity, strategy,
                    None if selected is not None else "multiple contextual candidates",
                ))
            elif len(candidates) == 1:
                references.append(self._result(
                    phrase, candidates, candidates[0], True, kind, target_entity, strategy,
                ))
            elif len(candidates) > 1:
                references.append(self._result(
                    phrase, candidates, None, False, kind, target_entity, strategy,
                    "multiple contextual candidates",
                ))
            else:
                references.append(self._result(
                    phrase, [], None, False, kind, target_entity, strategy,
                    "no contextual candidate",
                ))
        return references

    def _references(self, text: str, entity: str | None):
        patterns = (
            (r"\b(?:ese|esa|este|esta)\s+(proyecto|bug|tarea|incidente|actualizacion)\b",
             "singular", None, "context"),
            (r"\b(?:(?:el|la)\s+que\s+)?mencionamos\s+antes\b",
             "mentioned_before", entity, "previous"),
            (r"\b(?:el mismo|la misma)\s+(proyecto|bug|tarea|incidente)\b",
             "same", None, "context"),
            (r"\b(?:sus)\s+(tareas|bugs|incidentes|actualizaciones)\b",
             "possessive", None, "context"),
            (r"\b(?:el primero|la primera|los primeros|las primeras)\b",
             "position", entity, "first"),
            (r"\b(?:el ultimo|la ultima|los ultimos|las ultimas)\b",
             "position", entity, "last"),
            (r"\b(?:el anterior|la anterior|los anteriores|las anteriores)\b",
             "previous", entity, "previous"),
            (r"\b(?:los|las)\s+(pendientes|criticos|abiertos|abiertas)\b",
             "attribute", entity, "attribute"),
            (r"\b(?:ellos|ellas|cuáles|cuales|estos|estas)\b",
             "plural", entity, "context"),
            (r"\b(?:ese|esa|este|esta|eso)\b",
             "singular", entity, "context"),
        )
        for pattern, kind, default_entity, strategy in patterns:
            match = re.search(pattern, text)
            if not match:
                continue
            target = default_entity
            if kind in {"singular", "same"} and match.lastindex:
                target = self._canonical_entity(match.group(match.lastindex))
            if kind == "possessive":
                target = "proyecto"
            if target is None:
                target = entity
            yield match.group(0), kind, target, strategy

    def _candidates(
        self,
        records: list[MemoryRecord],
        entity: str | None,
        text: str,
        kind: str,
    ) -> list[dict[str, Any]]:
        candidates = []
        for record in records:
            value = record.content
            record_entity = value.get("entity")
            if entity and record_entity != entity:
                continue
            if kind == "possessive":
                # "sus tareas" resolves the antecedent project, not an invented task list.
                if record_entity != "proyecto":
                    continue
            items = value.get("items")
            if isinstance(items, list) and items:
                if kind in {"plural", "attribute"}:
                    selected_items = [
                        item for item in items
                        if isinstance(item, dict)
                        and (kind != "attribute" or self._matches_attribute(item, self._attribute(text)))
                    ]
                    if selected_items:
                        candidates.append({
                            "entity": record_entity,
                            "items": selected_items,
                            "project_id": value.get("project_id"),
                            "_record_id": record.record_id,
                            "_position": 0,
                        })
                else:
                    for index, item in enumerate(items):
                        if isinstance(item, dict):
                            candidates.append({
                                **item,
                                "entity": record_entity,
                                "project_id": value.get("project_id", item.get("project_id")),
                                "_record_id": record.record_id,
                                "_position": index,
                            })
            elif record_entity:
                candidates.append({**value, "_record_id": record.record_id, "_position": 0})

        if kind == "attribute":
            wanted = self._attribute(text)
            candidates = [
                candidate for candidate in candidates
                if "items" in candidate or self._matches_attribute(candidate, wanted)
            ]
        return candidates

    @staticmethod
    def _attribute(text: str) -> tuple[str, str] | None:
        text = unicodedata.normalize("NFD", text)
        text = "".join(char for char in text if unicodedata.category(char) != "Mn")
        if re.search(r"\bpendientes\b", text):
            return "status", "pendiente"
        if re.search(r"\babiert(?:os|as)\b", text):
            return "status", "abierto"
        if re.search(r"\bcriticos\b", text):
            return "priority", "alta"
        return None

    @staticmethod
    def _matches_attribute(candidate: dict[str, Any], wanted: tuple[str, str] | None) -> bool:
        if wanted is None:
            return True
        key, expected = wanted
        values = [candidate.get(key), candidate.get("estado") if key == "status" else candidate.get("prioridad")]
        return any(str(value).casefold() == expected for value in values if value is not None)

    def _result(
        self,
        phrase: str,
        candidates: list[dict[str, Any]],
        selected: dict[str, Any] | None,
        resolved: bool,
        kind: str,
        target_entity: str | None,
        strategy: str,
        reason: str | None = None,
    ) -> ContextReference:
        return ContextReference(
            text=phrase,
            resolved=resolved,
            candidates=self._public_candidates(candidates),
            selected=self._public_candidate(selected),
            requires_clarification=not resolved,
            reason=reason,
            reference_kind=kind,
            target_entity=target_entity,
            resolution_strategy=strategy,
        )

    @staticmethod
    def _public_candidate(candidate):
        if candidate is None:
            return None
        return {key: value for key, value in candidate.items() if not key.startswith("_")}

    def _public_candidates(self, candidates):
        return [self._public_candidate(candidate) for candidate in candidates]

    def _canonical_entity(self, value: str) -> str | None:
        for entity, aliases in self._ENTITY_ALIASES.items():
            if value in aliases:
                return entity
        return None
