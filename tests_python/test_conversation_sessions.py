import tempfile
import unittest
from datetime import timedelta
from pathlib import Path

from nexus_ai.api.application import NexusAiApplication
from nexus_ai.api.contracts import NexusAiRequest, NexusContext
from nexus_ai.memory import ContextManager, ConversationMemoryStore


class ConversationSessionTests(unittest.TestCase):
    def test_independent_process_instances_share_session_context(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "memory.json"
            first = NexusAiApplication(ContextManager(ConversationMemoryStore(path=path)))
            first.process(
                NexusAiRequest("¿Cuántos proyectos tengo?"),
                recovered_data={"proyecto": [{"id": 1, "nombre": "DevControl"}]},
                execution_results=[{"ok": True, "data": [{"id": 1, "nombre": "DevControl"}], "meta": {"entity": "proyecto"}}],
                session_id="session-a",
            )
            second = NexusAiApplication(ContextManager(ConversationMemoryStore(path=path)))
            response = second.process(
                NexusAiRequest("¿Y cuál tiene más bugs?"),
                recovered_data={"proyecto": [{"id": 1, "nombre": "DevControl"}]},
                session_id="session-a",
            )
            self.assertEqual(response.context_used["session_id"], "session-a")
            self.assertTrue(response.context_used["recent_turns"])

    def test_references_and_multiple_candidates(self):
        manager = ContextManager(ConversationMemoryStore())
        manager.remember_result("s", "bug", [{"id": 1}, {"id": 2}], "DevControl")
        snapshot = manager.snapshot("s", "¿Cuáles son abiertos?", "bug")
        self.assertTrue(snapshot.resolved_references[0].resolved)
        first = manager.snapshot("s", "¿Cuál es el primero?", "bug")
        self.assertTrue(first.resolved_references[0].resolved)

        manager.remember_result("s", "bug", [{"id": 3}], "Otro")
        ambiguous = manager.snapshot("s", "¿Cuáles son?", "bug")
        self.assertTrue(ambiguous.resolved_references[0].requires_clarification)

    def test_sessions_are_isolated_and_expired_context_is_removed(self):
        manager = ContextManager(ConversationMemoryStore(ttl_seconds=1))
        manager.remember_result("one", "bug", [{"id": 1}])
        self.assertTrue(manager.snapshot("two", "¿Y ese?", "bug").resolved_references[0].requires_clarification)
        record = manager.store.recent("one")[0]
        self.assertEqual(manager.store.recent("one", now=record.created_at + timedelta(seconds=2)), [])

    def test_context_does_not_store_permissions_or_execute_tools(self):
        manager = ContextManager(ConversationMemoryStore())
        manager.remember_turn("safe", "user", "consulta", {"permissions": ["devcontrol.write"]})
        stored = manager.store.recent("safe")[0].content
        self.assertNotIn("permissions", stored)


if __name__ == "__main__":
    unittest.main()
