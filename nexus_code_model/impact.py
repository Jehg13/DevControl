"""Evidence-only impact analysis over Code Intelligence results."""

from __future__ import annotations

from collections import deque
from dataclasses import asdict, dataclass
from typing import Any, Iterable

from .intelligence import CodeIntelligenceResult


@dataclass(frozen=True)
class ImpactEdge:
    source: str
    target: str
    relation: str
    direction: str
    impact_level: str
    evidence: dict[str, Any]

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


@dataclass(frozen=True)
class ImpactComponent:
    component: str
    component_type: str
    impact_level: str
    evidence: tuple[dict[str, Any], ...]

    def to_dict(self) -> dict[str, Any]:
        result = asdict(self)
        result["evidence"] = list(self.evidence)
        return result


@dataclass(frozen=True)
class ImpactGraph:
    changed: tuple[str, ...]
    components: tuple[ImpactComponent, ...]
    relations: tuple[ImpactEdge, ...]
    read_only: bool = True

    def to_dict(self) -> dict[str, Any]:
        return {
            "changed": list(self.changed),
            "components": [item.to_dict() for item in self.components],
            "relations": [item.to_dict() for item in self.relations],
            "read_only": self.read_only,
        }


class ImpactAnalyzer:
    """Finds only components connected by relationships in supplied evidence."""

    def analyze(
        self,
        changed: Iterable[dict[str, Any] | str],
        intelligence: CodeIntelligenceResult | dict[str, Any],
    ) -> ImpactGraph:
        result = self._result(intelligence)
        nodes = self._nodes(result)
        edges = self._edges(result, nodes)
        changed_ids = tuple(
            identifier for item in changed
            if (identifier := self._resolve(item, nodes)) is not None
        )
        adjacency: dict[str, list[ImpactEdge]] = {node: [] for node in nodes}
        for edge in edges:
            adjacency.setdefault(edge.source, []).append(edge)
            adjacency.setdefault(edge.target, []).append(
                ImpactEdge(
                    edge.target,
                    edge.source,
                    edge.relation,
                    "reverse",
                    edge.impact_level,
                    edge.evidence,
                )
            )

        distance: dict[str, int] = {item: 0 for item in changed_ids}
        evidence: dict[str, list[dict[str, Any]]] = {item: [] for item in changed_ids}
        queue = deque(changed_ids)
        while queue:
            current = queue.popleft()
            for edge in adjacency.get(current, []):
                if edge.target in distance:
                    continue
                distance[edge.target] = distance[current] + 1
                evidence[edge.target] = [edge.evidence]
                queue.append(edge.target)

        impacted = []
        for identifier, level in distance.items():
            if level == 0:
                continue
            node = nodes[identifier]
            impacted.append(ImpactComponent(
                identifier,
                node["type"],
                "direct" if level == 1 else "transitive",
                tuple(evidence[identifier]),
            ))
        graph_edges = tuple(
            ImpactEdge(
                edge.source,
                edge.target,
                edge.relation,
                edge.direction,
                "direct" if distance.get(edge.source, 999) + 1 == distance.get(edge.target, 999)
                else edge.impact_level,
                edge.evidence,
            )
            for edge in edges
            if edge.source in distance and edge.target in distance
        )
        return ImpactGraph(changed_ids, tuple(impacted), graph_edges)

    @staticmethod
    def _result(value: CodeIntelligenceResult | dict[str, Any]) -> CodeIntelligenceResult:
        if isinstance(value, CodeIntelligenceResult):
            return value
        return CodeIntelligenceResult.from_tool_result({"ok": True, "data": value})

    @staticmethod
    def _nodes(result: CodeIntelligenceResult) -> dict[str, dict[str, Any]]:
        nodes: dict[str, dict[str, Any]] = {}
        for item in result.files:
            nodes[item["path"]] = {
                "type": "file",
                "kind": item.get("component", "source"),
            }
        for item in result.directories:
            nodes[item["path"]] = {"type": "directory", "kind": "directory"}
        for item in result.symbols:
            identifier = f"{item['file']}::{item['name']}"
            nodes[identifier] = {"type": item["kind"], "kind": item["kind"], "file": item["file"]}
        return nodes

    @classmethod
    def _edges(cls, result: CodeIntelligenceResult, nodes: dict[str, dict[str, Any]]) -> tuple[ImpactEdge, ...]:
        edges: list[ImpactEdge] = []
        for relation in result.relations:
            source = cls._relation_node(relation.get("source"), nodes)
            target = cls._relation_node(relation.get("target"), nodes)
            if source and target and source != target:
                edges.append(ImpactEdge(
                    source, target, str(relation.get("type", "related")),
                    "forward", "direct", {"source": "code_intelligence_relation", "relation": relation},
                ))
        for item in result.imports:
            source = cls._relation_node(item.get("file"), nodes)
            target = cls._resolve_import(item.get("target"), item.get("file"), nodes)
            if source and target:
                edges.append(ImpactEdge(
                    source, target, "imports", "forward", "direct",
                    {"source": "code_intelligence_import", "import": item},
                ))
        for symbol in result.symbols:
            file_name = symbol["file"]
            symbol_name = f"{file_name}::{symbol['name']}"
            if file_name in nodes:
                edges.append(ImpactEdge(
                    file_name, symbol_name, "contains_symbol", "forward", "direct",
                    {"source": "code_intelligence_symbol", "symbol": symbol},
                ))
        unique: list[ImpactEdge] = []
        seen: set[tuple[str, str, str, str]] = set()
        for edge in edges:
            key = (edge.source, edge.target, edge.relation, edge.direction)
            if key not in seen:
                seen.add(key)
                unique.append(edge)
        return tuple(unique)

    @staticmethod
    def _relation_node(value: Any, nodes: dict[str, dict[str, Any]]) -> str | None:
        if not isinstance(value, str):
            return None
        if value in nodes:
            return value
        matches = [key for key in nodes if key.endswith(f"::{value}")]
        return matches[0] if len(matches) == 1 else None

    @classmethod
    def _resolve_import(cls, target: Any, source: Any, nodes: dict[str, dict[str, Any]]) -> str | None:
        if not isinstance(target, str) or not isinstance(source, str):
            return None
        if target in nodes:
            return target
        normalized = target.replace("\\", "/")
        if not normalized.startswith("."):
            return None
        base = source.rsplit("/", 1)[0] if "/" in source else ""
        candidate = "/".join(part for part in (base + "/" + normalized).split("/") if part not in ("", "."))
        candidates = [candidate, *[f"{candidate}.{extension}" for extension in ("php", "js", "jsx", "ts", "tsx", "py", "dart")]]
        matches = [item for item in candidates if item in nodes]
        return matches[0] if len(matches) == 1 else None

    @staticmethod
    def _resolve(value: dict[str, Any] | str, nodes: dict[str, dict[str, Any]]) -> str | None:
        candidate = value if isinstance(value, str) else value.get("id") or value.get("path") or value.get("name")
        if not isinstance(candidate, str):
            return None
        return candidate if candidate in nodes else ImpactAnalyzer._relation_node(candidate, nodes)
