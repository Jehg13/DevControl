"""Anaphora and entity resolution over conversation memory."""

from typing import Any

from .models import ContextReference, MemoryRecord


class ContextResolver:
    _PLURAL = {"ellos", "ellas", "cuáles", "cuales", "estos", "estas"}
    _SINGULAR = {"ese", "esa", "este", "esta", "eso", "anterior"}

    def resolve(self, text: str, records: list[MemoryRecord], entity: str | None = None) -> list[ContextReference]:
        normalized = text.casefold()
        references = []
        for token in self._PLURAL | self._SINGULAR:
            if token not in normalized:
                continue
            candidates = self._candidates(records, entity, plural=token in self._PLURAL)
            if len(candidates) == 1:
                references.append(ContextReference(token, True, candidates, candidates[0]))
            elif len(candidates) > 1:
                references.append(ContextReference(token, False, candidates, None, True, "multiple contextual candidates"))
            else:
                references.append(ContextReference(token, False, [], None, True, "no contextual candidate"))
        return references

    def _candidates(self, records: list[MemoryRecord], entity: str | None, plural: bool) -> list[dict[str, Any]]:
        candidates: list[dict[str, Any]] = []
        for record in records:
            value = record.content
            if entity and value.get("entity") != entity:
                continue
            if plural and value.get("entity") not in {"bug", "tarea", "incidente", "actualizacion"}:
                continue
            if any(key in value for key in ("entity", "items", "project_id")):
                items = value.get("items") or []
                if not plural and len(items) == 1:
                    candidates.append({**items[0], "entity": value.get("entity"), "project_id": value.get("project_id")})
                elif not plural and len(items) > 1:
                    candidates.extend(
                        {**item, "entity": value.get("entity"), "project_id": value.get("project_id")}
                        for item in items
                    )
                else:
                    candidates.append(value)
        return candidates[:5]
