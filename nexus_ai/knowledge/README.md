# Nexus knowledge projection

The knowledge layer is a graph-shaped projection of snapshots supplied by
Laravel. Laravel remains the source of truth; this package does not connect to
its database or duplicate persistence.

Assertions are explicitly classified as:

- `fact`: a scalar property such as a status or priority;
- `relation`: an edge between two imported nodes;
- `inference`: reserved for future reasoning and excluded from normal queries.

`KnowledgeGraph.update()` replaces the previous snapshot from the same source,
so changes in DevControl can be reflected without accumulating stale records.
No assertion is converted from an inference into a fact automatically.
