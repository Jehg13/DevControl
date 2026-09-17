"""Translate NLP interpretations into constrained Laravel query requests."""

from dataclasses import asdict, dataclass, field
from typing import Any


ALLOWED_ENTITIES = {
    "proyecto", "tarea", "bug", "incidente", "actualizacion", "usuario",
}
ALLOWED_OPERATIONS = {
    "list", "search", "count", "detail", "filter", "sort", "group",
    "compare", "statistics", "relation",
}
FILTER_KEYS = {"project_id", "project_name", "status", "priority", "user_id"}
SORT_FIELDS = {
    "id", "nombre", "name", "titulo", "title", "status", "priority",
    "bugs_count", "pending_tasks_count",
}
GROUP_FIELDS = {"project_id", "status", "priority", "user_id"}


@dataclass(frozen=True)
class DevControlQuery:
    """Serializable request; it contains no SQL and cannot execute itself."""

    entity: str
    operation: str
    filters: dict[str, Any] = field(default_factory=dict)
    sort: dict[str, str] = field(default_factory=dict)
    group_by: list[str] = field(default_factory=list)
    relations: list[str] = field(default_factory=list)
    limit: int | None = None
    context_requirements: list[str] = field(default_factory=list)
    permission_scope: str = "devcontrol.read"
    executable: bool = False

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class SemanticQueryBuilder:
    """Builds only allowlisted semantic requests for Laravel."""

    def build(self, interpretation: dict[str, Any]) -> DevControlQuery | None:
        if interpretation.get("action") != "query":
            return None
        if interpretation.get("requires_clarification"):
            return None
        entity = interpretation.get("entity")
        if entity not in ALLOWED_ENTITIES:
            return None
        operation = interpretation.get("operation", "list")
        if operation not in ALLOWED_OPERATIONS:
            operation = "list"
        filters = {
            key: value for key, value in (interpretation.get("filters") or {}).items()
            if key in FILTER_KEYS and isinstance(value, (str, int, bool))
        }
        sort = interpretation.get("sort") or {}
        safe_sort = {}
        if sort.get("field") in SORT_FIELDS and sort.get("direction") in {"asc", "desc"}:
            safe_sort = {"field": sort["field"], "direction": sort["direction"]}
        group_by = [
            field for field in interpretation.get("group_by", [])
            if field in GROUP_FIELDS
        ]
        relations = [
            relation for relation in interpretation.get("relations", [])
            if relation in ALLOWED_ENTITIES
        ]
        limit = interpretation.get("limit")
        if not isinstance(limit, int) or not 1 <= limit <= 100:
            limit = None
        return DevControlQuery(
            entity=entity,
            operation=operation,
            filters=filters,
            sort=safe_sort,
            group_by=group_by,
            relations=relations,
            limit=limit,
            context_requirements=list(interpretation.get("context_requirements", [])),
        )
