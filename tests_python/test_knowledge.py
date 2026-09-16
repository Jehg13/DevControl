import unittest

from nexus_ai.knowledge import KnowledgeAssertion, KnowledgeGraph, KnowledgeNode, KnowledgeSnapshot, KnowledgeValidationError


class KnowledgeGraphTests(unittest.TestCase):
    def test_builds_devcontrol_projection_and_queries_facts(self):
        graph = KnowledgeGraph.from_devcontrol(
            {
                "proyecto": [{"id": 1, "name": "DevControl"}],
                "usuario": [{"id": 7, "name": "Admin"}],
                "bug": [
                    {
                        "id": 4,
                        "project_id": 1,
                        "assigned_to": 7,
                        "status": "abierto",
                        "priority": "critica",
                    }
                ],
            }
        )

        self.assertEqual(graph.related("bug:4", "belongs_to")[0].node_id, "proyecto:1")
        self.assertEqual(graph.related("bug:4", "assigned_to")[0].node_id, "usuario:7")
        self.assertEqual(graph.query(subject="bug:4", predicate="has_priority")[0].object, "critica")

    def test_updates_a_source_without_retaining_stale_snapshot(self):
        graph = KnowledgeGraph.from_devcontrol({"bug": [{"id": 4, "status": "abierto"}]})
        graph.update(
            KnowledgeSnapshot(
                "laravel:devcontrol",
                "2",
                (KnowledgeNode("bug:4", "bug"),),
                (KnowledgeAssertion("bug:4", "has_status", "cerrado", "fact", "laravel:devcontrol"),),
            )
        )

        self.assertEqual(graph.query(subject="bug:4", predicate="has_status")[0].object, "cerrado")
        self.assertEqual(len(graph.query(subject="bug:4", predicate="has_status")), 1)

    def test_inferences_are_distinguished_and_hidden_by_default(self):
        assertion = KnowledgeAssertion("bug:4", "may_block", "tarea:8", "inference", "nexus:reasoning", 0.6)
        graph = KnowledgeGraph()
        graph.update(KnowledgeSnapshot("nexus:reasoning", "1", tuple(), (assertion,)))

        self.assertEqual(graph.query(subject="bug:4"), [])
        self.assertEqual(graph.query(subject="bug:4", include_inferences=True)[0].assertion_type, "inference")

    def test_rejects_relations_to_unknown_nodes(self):
        with self.assertRaises(KnowledgeValidationError):
            KnowledgeGraph().update(
                KnowledgeSnapshot(
                    "source",
                    "1",
                    (KnowledgeNode("bug:4", "bug"),),
                    (KnowledgeAssertion("bug:4", "related_to", "task:8", "relation", "source"),),
                )
            )

    def test_rejects_malformed_entity_payload(self):
        with self.assertRaises(KnowledgeValidationError):
            KnowledgeGraph.from_devcontrol({"bug": [{"status": "abierto"}]})


if __name__ == "__main__":
    unittest.main()
