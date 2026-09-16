import unittest
from datetime import datetime, timedelta, timezone

from nexus_ai.memory import ContextManager, ConversationMemoryStore, ContextResolver


class NexusMemoryTests(unittest.TestCase):
    def test_previous_bug_result_resolves_plural_follow_up(self):
        manager = ContextManager()
        manager.remember_result("s1", "bug", [{"id": "BUG-1"}, {"id": "BUG-2"}], "DevControl")

        snapshot = manager.snapshot("s1", "¿Cuáles son críticos?", "bug")

        self.assertTrue(snapshot.resolved_references[0].resolved)
        self.assertEqual(snapshot.resolved_references[0].selected["entity"], "bug")
        self.assertFalse(snapshot.resolved_references[0].requires_clarification)

    def test_ese_esa_and_ellos_references(self):
        manager = ContextManager()
        manager.remember_result("s1", "bug", [{"id": "BUG-1"}])
        manager.remember_result("s1", "tarea", [{"id": "8"}])

        ese = manager.snapshot("s1", "Elimina ese bug", "bug")
        esa = manager.snapshot("s1", "Actualiza esa tarea", "tarea")
        ellos = manager.snapshot("s1", "¿Cuáles son?", None)

        self.assertTrue(ese.resolved_references[0].resolved)
        self.assertTrue(esa.resolved_references[0].resolved)
        self.assertTrue(ellos.resolved_references[0].requires_clarification)

    def test_previous_reference_is_resolved_when_single_candidate_exists(self):
        manager = ContextManager()
        manager.remember_result("s1", "bug", [{"id": "BUG-9"}])

        snapshot = manager.snapshot("s1", "¿Y el anterior?", "bug")

        self.assertTrue(snapshot.resolved_references[0].resolved)

    def test_multiple_projects_remain_separate(self):
        manager = ContextManager()
        manager.remember_result("s1", "bug", [{"id": "BUG-1"}], "DevControl")
        manager.remember_result("s1", "bug", [{"id": "BUG-2"}], "OtroProyecto")

        snapshot = manager.snapshot("s1", "¿Cuáles son?", "bug")

        self.assertTrue(snapshot.resolved_references[0].requires_clarification)
        self.assertEqual(len(snapshot.resolved_references[0].candidates), 2)

    def test_new_session_has_no_context(self):
        snapshot = ContextManager().snapshot("new", "Elimina ese bug", "bug")

        self.assertTrue(snapshot.resolved_references[0].requires_clarification)
        self.assertEqual(snapshot.resolved_references[0].reason, "no contextual candidate")

    def test_expired_context_is_removed(self):
        store = ConversationMemoryStore(ttl_seconds=1)
        record = store.add("s1", "result", {"entity": "bug", "items": []})
        future = record.created_at + timedelta(seconds=2)

        self.assertEqual(store.recent("s1", now=future), [])

    def test_contradictions_are_exposed_not_resolved_by_memory(self):
        manager = ContextManager()
        manager.remember_result("s1", "bug", [{"id": "BUG-1", "status": "Abierto"}])
        manager.remember_result("s1", "bug", [{"id": "BUG-1", "status": "Cerrado"}])

        snapshot = manager.snapshot("s1", "¿Qué pasó?", "bug")

        self.assertTrue(snapshot.contradictions)
        self.assertEqual(snapshot.contradictions[0]["key"], "bug")

    def test_persistent_record_can_be_exported_without_execution(self):
        store = ConversationMemoryStore()
        store.add("s1", "knowledge_reference", {"entity": "proyecto"}, persistent=True)

        self.assertIsNone(store.recent("s1")[0].expires_at)


if __name__ == "__main__":
    unittest.main()
