import unittest
from datetime import timedelta

from nexus_ai.memory import ContextManager, ConversationMemoryStore


class RobustReferenceResolutionTests(unittest.TestCase):
    def setUp(self):
        self.manager = ContextManager(ConversationMemoryStore())

    def test_resolves_project_by_entity_and_same_project(self):
        self.manager.remember_result("s", "proyecto", [{"id": 1, "nombre": "DevControl"}])

        for text in ("¿Qué tiene ese proyecto?", "¿Qué tiene el mismo proyecto?"):
            reference = self.manager.snapshot("s", text).resolved_references[0]
            self.assertTrue(reference.resolved)
            self.assertEqual(reference.target_entity, "proyecto")
            self.assertEqual(reference.selected["id"], 1)

    def test_resolves_first_last_and_previous_by_position(self):
        self.manager.remember_result("s", "bug", [{"id": 1}, {"id": 2}, {"id": 3}])
        first = self.manager.snapshot("s", "¿Cuál es el primero?", "bug").resolved_references[0]
        last = self.manager.snapshot("s", "¿Cuál es el último?", "bug").resolved_references[0]

        self.assertEqual(first.selected["id"], 1)
        self.assertEqual(last.selected["id"], 3)
        self.assertEqual(first.resolution_strategy, "first")
        self.assertEqual(last.resolution_strategy, "last")

    def test_resolves_plural_and_attribute_references(self):
        self.manager.remember_result(
            "s",
            "bug",
            [
                {"id": 1, "status": "Abierto", "priority": "Alta"},
                {"id": 2, "status": "Cerrado", "priority": "Baja"},
            ],
        )
        pending = self.manager.snapshot("s", "¿Cuáles son los críticos?", "bug").resolved_references[0]

        self.assertTrue(pending.resolved)
        self.assertEqual(pending.reference_kind, "attribute")
        self.assertEqual([item["id"] for item in pending.selected["items"]], [1])

    def test_resolves_possessive_against_project_without_inventing_tasks(self):
        self.manager.remember_result("s", "proyecto", [{"id": 4, "nombre": "DevControl"}])
        reference = self.manager.snapshot("s", "¿Y sus tareas?").resolved_references[0]

        self.assertTrue(reference.resolved)
        self.assertEqual(reference.target_entity, "proyecto")
        self.assertEqual(reference.selected["id"], 4)
        self.assertNotIn("tareas", reference.selected)

    def test_mentioned_before_is_ambiguous_with_multiple_candidates(self):
        self.manager.remember_result("s", "proyecto", [{"id": 1}])
        self.manager.remember_result("s", "proyecto", [{"id": 2}])
        reference = self.manager.snapshot("s", "¿Cuál mencionamos antes?", "proyecto").resolved_references[0]

        self.assertFalse(reference.resolved)
        self.assertTrue(reference.requires_clarification)
        self.assertEqual(reference.reason, "multiple contextual candidates")

    def test_expiration_invalidates_reference(self):
        store = ConversationMemoryStore(ttl_seconds=1)
        manager = ContextManager(store)
        manager.remember_result("s", "bug", [{"id": 1}])
        record = store.recent("s")[0]

        self.assertTrue(
            manager.snapshot("s", "¿Qué pasa con ese bug?", "bug").resolved_references[0].resolved
        )
        self.assertEqual(store.recent("s", now=record.created_at + timedelta(seconds=2)), [])

    def test_sessions_never_share_referents(self):
        self.manager.remember_result("one", "proyecto", [{"id": 1}])

        reference = self.manager.snapshot("two", "¿Qué pasa con ese proyecto?", "proyecto").resolved_references[0]

        self.assertFalse(reference.resolved)
        self.assertTrue(reference.requires_clarification)
        self.assertEqual(reference.candidates, [])


if __name__ == "__main__":
    unittest.main()
