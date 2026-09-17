"""An authoritative-data projection for relationship queries.

Laravel remains the system of record. This graph only holds explicitly imported
snapshots and never invents or persists DevControl records.
"""

from typing import Any

from .models import KnowledgeAssertion, KnowledgeNode, KnowledgeSnapshot


class KnowledgeValidationError(ValueError):
    """Raised when an imported knowledge snapshot is malformed."""


class KnowledgeGraph:
    _RELATION_FIELDS = {
        "project_id": "proyecto",
        "user_id": "usuario",
        "assigned_to": "usuario",
        "created_by": "usuario",
        "updated_by": "usuario",
        "task_id": "tarea",
        "bug_id": "bug",
        "incident_id": "incidente",
        "update_id": "actualizacion",
        "event_id": "evento",
    }

    def __init__(self) -> None:
        self._nodes: dict[str, KnowledgeNode] = {}
        self._assertions: list[KnowledgeAssertion] = []
        self._sources: set[str] = set()

    def update(self, snapshot: KnowledgeSnapshot) -> None:
        if not snapshot.source.strip() or not snapshot.version.strip():
            raise KnowledgeValidationError("snapshot source and version are required")
        if len({node.node_id for node in snapshot.nodes}) != len(snapshot.nodes):
            raise KnowledgeValidationError("snapshot contains duplicate node ids")
        known_ids = {node.node_id for node in snapshot.nodes}
        for assertion in snapshot.assertions:
            if assertion.assertion_type == "relation" and (
                assertion.subject not in known_ids or not isinstance(assertion.object, str) or assertion.object not in known_ids
            ):
                raise KnowledgeValidationError("relations must reference nodes in the same snapshot")
        authoritative = snapshot.source.startswith("laravel:")
        if authoritative:
            self._assertions = [
                assertion for assertion in self._assertions
                if assertion.assertion_type == "inference"
                or (assertion.subject not in known_ids and assertion.object not in known_ids)
            ]
        else:
            self._assertions = [
                assertion for assertion in self._assertions if assertion.source != snapshot.source
            ]
        self._nodes = {
            node_id: node for node_id, node in self._nodes.items() if node_id not in known_ids
        }
        self._nodes.update({node.node_id: node for node in snapshot.nodes})
        self._assertions.extend(snapshot.assertions)
        self._sources.add(snapshot.source)

    def nodes(self, node_type: str | None = None) -> list[KnowledgeNode]:
        values = list(self._nodes.values())
        return [node for node in values if node_type is None or node.node_type == node_type]

    def query(
        self,
        *,
        subject: str | None = None,
        predicate: str | None = None,
        object_value: Any = None,
        assertion_type: str | None = None,
        include_inferences: bool = False,
    ) -> list[KnowledgeAssertion]:
        return [
            assertion
            for assertion in self._assertions
            if (subject is None or assertion.subject == subject)
            and (predicate is None or assertion.predicate == predicate)
            and (object_value is None or assertion.object == object_value)
            and (assertion_type is None or assertion.assertion_type == assertion_type)
            and (include_inferences or assertion.assertion_type != "inference")
        ]

    def related(self, node_id: str, predicate: str | None = None) -> list[KnowledgeNode]:
        related_ids = [
            assertion.object
            for assertion in self.query(subject=node_id, predicate=predicate, assertion_type="relation")
            if isinstance(assertion.object, str)
        ]
        return [self._nodes[related_id] for related_id in related_ids if related_id in self._nodes]

    def related_from(self, node_id: str, predicate: str | None = None) -> list[KnowledgeNode]:
        """Return incoming relationships without treating inference as fact."""
        related_ids = [
            assertion.subject
            for assertion in self.query(predicate=predicate, object_value=node_id, assertion_type="relation")
        ]
        return [self._nodes[related_id] for related_id in related_ids if related_id in self._nodes]

    def project_context(self, project_id: str) -> dict[str, Any]:
        """Navigate the current project subgraph without becoming a data source."""
        project = self._nodes.get(project_id)
        if project is None or project.node_type != "proyecto":
            return {"project": None, "related": {}, "dependencies": []}
        related: dict[str, list[dict[str, Any]]] = {}
        for node_type in ("tarea", "bug", "incidente", "actualizacion", "usuario", "evento"):
            related[node_type] = [
                node.to_dict() for node in self.related_from(project_id)
                if node.node_type == node_type
            ]
        return {
            "project": project.to_dict(),
            "related": related,
            "dependencies": self.dependencies(project_id),
        }

    def dependencies(self, node_id: str) -> list[dict[str, Any]]:
        """Return explicit dependency edges only; inferred edges stay excluded."""
        return [
            assertion.to_dict()
            for assertion in self.query(
                subject=node_id,
                predicate="depends_on",
                assertion_type="relation",
            )
        ]

    def relation_paths(self, start: str, end: str, max_depth: int = 3) -> list[list[str]]:
        """Find short explicit paths for semantic navigation."""
        if max_depth < 1:
            return []
        paths: list[list[str]] = []
        queue = [(start, [start])]
        while queue:
            current, path = queue.pop(0)
            if current == end:
                paths.append(path)
                continue
            if len(path) - 1 >= max_depth:
                continue
            for node in self.related(current):
                if node.node_id not in path:
                    queue.append((node.node_id, [*path, node.node_id]))
        return paths

    def sources(self) -> set[str]:
        return set(self._sources)

    def export(self) -> dict[str, Any]:
        return {
            "nodes": [node.to_dict() for node in self._nodes.values()],
            "assertions": [assertion.to_dict() for assertion in self._assertions],
            "sources": sorted(self._sources),
        }

    @classmethod
    def from_devcontrol(cls, payload: dict[str, Any], source: str = "laravel:devcontrol", version: str = "1") -> "KnowledgeGraph":
        nodes: list[KnowledgeNode] = []
        assertions: list[KnowledgeAssertion] = []
        normalized_payload = {
            cls._canonical_type(entity_type): records for entity_type, records in payload.items()
        }
        known_node_ids = {
            f"{entity_type}:{record['id']}"
            for entity_type, records in normalized_payload.items()
            if isinstance(records, list)
            for record in records
            if isinstance(record, dict) and record.get("id")
        }
        for entity_type, records in normalized_payload.items():
            if not isinstance(records, list):
                raise KnowledgeValidationError(f"{entity_type} must be a list")
            for record in records:
                if not isinstance(record, dict) or not record.get("id"):
                    raise KnowledgeValidationError(f"{entity_type} records require an id")
                node_id = f"{entity_type}:{record['id']}"
                nodes.append(
                    KnowledgeNode(
                        node_id,
                        entity_type,
                        record.get("name") or record.get("nombre") or record.get("title") or record.get("titulo"),
                        {
                            key: value for key, value in record.items()
                            if key not in {"id", "name", "nombre", "title", "titulo"}
                        },
                    )
                )
                for field, aliases in {
                    "status": ("status", "estado"),
                    "priority": ("priority", "prioridad"),
                }.items():
                    value = next((record.get(alias) for alias in aliases if record.get(alias) is not None), None)
                    if value is not None:
                        assertions.append(KnowledgeAssertion(node_id, f"has_{field}", value, "fact", source))
                for field, target_type in cls._RELATION_FIELDS.items():
                    target_id = record.get(field)
                    if target_id is None and field == "project_id":
                        target_id = record.get("proyecto_id")
                    if target_id is None and field == "user_id":
                        target_id = record.get("usuario_id")
                    if target_id is not None:
                        predicate = "belongs_to" if field == "project_id" else field
                        target_node_id = f"{target_type}:{target_id}"
                        if target_node_id in known_node_ids:
                            assertions.append(
                                KnowledgeAssertion(
                                    node_id,
                                    predicate,
                                    target_node_id,
                                    "relation",
                                    source,
                                )
                            )
                for dependency in ("depends_on", "depends_on_id", "blocked_by", "blocked_by_id"):
                    target_id = record.get(dependency)
                    if target_id is not None:
                        target_node_id = f"tarea:{target_id}"
                        if target_node_id in known_node_ids:
                            assertions.append(KnowledgeAssertion(node_id, "depends_on", target_node_id, "relation", source))
        graph = cls()
        graph.update(KnowledgeSnapshot(source, version, tuple(nodes), tuple(assertions)))
        return graph

    @staticmethod
    def _canonical_type(entity_type: str) -> str:
        aliases = {
            "proyectos": "proyecto", "projects": "proyecto",
            "tareas": "tarea", "tasks": "tarea",
            "bugs": "bug", "incidentes": "incidente", "incidents": "incidente",
            "actualizaciones": "actualizacion", "updates": "actualizacion",
            "usuarios": "usuario", "users": "usuario",
            "eventos": "evento", "events": "evento", "evento": "evento",
        }
        return aliases.get(entity_type, entity_type)
