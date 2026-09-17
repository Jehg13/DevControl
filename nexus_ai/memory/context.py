"""Session context assembly; current DevControl data remains authoritative."""

from .models import ContextSnapshot
from .resolver import ContextResolver
from .store import ConversationMemoryStore


class ContextManager:
    def __init__(self, store: ConversationMemoryStore | None = None, resolver: ContextResolver | None = None):
        self.store = store or ConversationMemoryStore()
        self.resolver = resolver or ContextResolver()

    def remember_turn(self, session_id: str, role: str, content: str, interpretation: dict | None = None) -> None:
        safe_interpretation = {}
        if interpretation:
            for key in (
                "intent", "entity", "action", "operation", "filters", "sort",
                "entities", "related_entities",
            ):
                if key in interpretation:
                    safe_interpretation[key] = interpretation[key]
        self.store.add(session_id, "turn", {"role": role, "text": content, "interpretation": safe_interpretation})

    def remember_result(self, session_id: str, entity: str, items: list[dict], project_id: str | None = None) -> None:
        compact = []
        for item in items[:20]:
            if not isinstance(item, dict):
                continue
            compact.append({
                key: item[key] for key in (
                    "id", "nombre", "name", "titulo", "title", "status", "estado",
                    "priority", "prioridad", "project_id", "proyecto_id",
                ) if key in item
            })
        self.store.add(session_id, "result", {
            "entity": entity,
            "items": compact,
            "project_id": project_id,
        })

    def snapshot(self, session_id: str, message: str, entity: str | None = None) -> ContextSnapshot:
        records = self.store.recent(session_id)
        references = self.resolver.resolve(message, records, entity)
        contradictions = self._contradictions(records)
        return ContextSnapshot(
            session_id=session_id,
            recent_turns=[record.content for record in records if record.kind == "turn"],
            relevant_records=[record.content for record in self.store.search(session_id, set(message.casefold().split()))],
            resolved_references=references,
            contradictions=contradictions,
        )

    def _contradictions(self, records):
        grouped = {}
        for record in records:
            content = record.content
            key = content.get("entity") or content.get("project_id")
            if key is not None:
                grouped.setdefault(key, []).append(content)
        return [
            {"key": key, "records": values}
            for key, values in grouped.items()
            if len({str(value) for value in values}) > 1
        ]
