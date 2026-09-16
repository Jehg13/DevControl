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
        self._assertions = [assertion for assertion in self._assertions if assertion.source != snapshot.source]
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
        for entity_type, records in payload.items():
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
                        record.get("name") or record.get("title"),
                        {"source_id": record["id"]},
                    )
                )
                for field in ("status", "priority"):
                    if record.get(field) is not None:
                        assertions.append(KnowledgeAssertion(node_id, f"has_{field}", record[field], "fact", source))
                for field, target_type in cls._RELATION_FIELDS.items():
                    target_id = record.get(field)
                    if target_id is not None:
                        predicate = "belongs_to" if field == "project_id" else field
                        assertions.append(
                            KnowledgeAssertion(
                                node_id,
                                predicate,
                                f"{target_type}:{target_id}",
                                "relation",
                                source,
                            )
                        )
        graph = cls()
        graph.update(KnowledgeSnapshot(source, version, tuple(nodes), tuple(assertions)))
        return graph
