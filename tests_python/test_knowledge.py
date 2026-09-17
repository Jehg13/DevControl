import unittest

from nexus_ai.knowledge import KnowledgeAssertion, KnowledgeGraph, KnowledgeNode, KnowledgeSnapshot, KnowledgeValidationError


class KnowledgeGraphTests(unittest.TestCase):
    def test_builds_devcontrol_projection_and_queries_facts(self):
        graph = KnowledgeGraph.from_devcontrol(
            {
                "proyecto": [{"id": 1, "name": "DevControl"}],
                "usuario": [{"id": 7, "name": "Admin"}],
                "evento": [{"id": 10, "project_id": 1, "name": "Release"}],
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

    def test_builds_operational_project_context_and_incoming_relations(self):
        graph = KnowledgeGraph.from_devcontrol({
            "proyecto": [{"id": 1, "nombre": "DevControl"}],
            "tarea": [{"id": 8, "proyecto_id": 1, "estado": "Pendiente", "depends_on_id": 9}],
            "bug": [{"id": 4, "proyecto_id": 1, "prioridad": "Alta"}],
            "incidente": [{"id": 2, "proyecto_id": 1}],
            "actualizacion": [{"id": 3, "proyecto_id": 1}],
            "usuario": [{"id": 7, "name": "Admin"}],
            "evento": [{"id": 10, "project_id": 1, "name": "Release"}],
        })

        context = graph.project_context("proyecto:1")
        self.assertEqual(context["project"]["label"], "DevControl")
        self.assertEqual([item["node_id"] for item in context["related"]["bug"]], ["bug:4"])
        self.assertEqual([item["node_id"] for item in context["related"]["evento"]], ["evento:10"])
        self.assertEqual(graph.related_from("proyecto:1")[0].node_id, "tarea:8")

    def test_dependencies_and_relation_paths_are_explicit(self):
        graph = KnowledgeGraph.from_devcontrol({
            "proyecto": [{"id": 1}],
            "tarea": [
                {"id": 8, "proyecto_id": 1, "depends_on_id": 9},
                {"id": 9, "proyecto_id": 1},
            ],
        })

        self.assertEqual(graph.dependencies("tarea:8")[0]["object"], "tarea:9")
        self.assertIn(["tarea:8", "tarea:9"], graph.relation_paths("tarea:8", "tarea:9"))

    def test_authoritative_laravel_snapshot_overrides_stale_non_authoritative_facts(self):
        graph = KnowledgeGraph()
        graph.update(KnowledgeSnapshot(
            "nexus:inference", "1", (KnowledgeNode("bug:4", "bug"),),
            (KnowledgeAssertion("bug:4", "has_status", "abierto", "fact", "nexus:inference"),),
        ))
        graph.update(KnowledgeSnapshot(
            "laravel:devcontrol", "2", (KnowledgeNode("bug:4", "bug"),),
            (KnowledgeAssertion("bug:4", "has_status", "cerrado", "fact", "laravel:devcontrol"),),
        ))

        facts = graph.query(subject="bug:4", predicate="has_status")
        self.assertEqual(len(facts), 1)
        self.assertEqual(facts[0].object, "cerrado")


if __name__ == "__main__":
    unittest.main()
