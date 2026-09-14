import json
import tempfile
import unittest
from pathlib import Path

from nexus_context import ContextItem, ContextRetriever, evaluate_retrieval
from nexus_tokenizer import NexusTokenizer


class NexusContextTest(unittest.TestCase):
    def setUp(self):
        self.tokenizer = NexusTokenizer.train(
            ["migration users deployment logs project alpha beta"],
            vocab_size=320,
            min_frequency=1,
        )
        self.items = [
            ContextItem("knowledge-migration", "knowledge", "Migration users failed in project alpha.", "alpha"),
            ContextItem("project-beta-secret", "knowledge", "Migration users project beta unrelated.", "beta"),
            ContextItem("conversation-current", "conversation", "Current deployment logs show failed migration.", "alpha", "session-2"),
            ContextItem("conversation-old", "conversation", "Old deployment logs from previous session.", "alpha", "session-1"),
        ]

    def test_retrieval_respects_project_session_and_budget(self):
        retriever = ContextRetriever(self.tokenizer, self.items)
        context = retriever.retrieve(
            "failed migration",
            project_id="alpha",
            session_id="session-2",
            max_items=2,
            max_tokens=1000,
        )
        self.assertEqual([item.item_id for item in context.items], ["conversation-current"])
        self.assertLessEqual(len(context.token_ids), 1000)

    def test_evaluation_measures_quality_and_contamination(self):
        with tempfile.TemporaryDirectory() as directory:
            benchmark = Path(directory) / "benchmark.json"
            benchmark.write_text(json.dumps([{
                "id": "case",
                "query": "migration users",
                "project_id": "alpha",
                "relevant_ids": ["knowledge-migration"],
                "forbidden_ids": ["project-beta-secret"],
                "max_items": 2,
                "max_tokens": 128,
            }]), encoding="utf-8")
            report = evaluate_retrieval(ContextRetriever(self.tokenizer, self.items), benchmark)
            self.assertEqual(report["cases"], 1)
            self.assertEqual(report["mean_contamination"], 0.0)

    def test_rejects_unknown_memory_type(self):
        with self.assertRaises(ValueError):
            ContextRetriever(self.tokenizer, [ContextItem("bad", "unknown", "text")])


if __name__ == "__main__":
    unittest.main()
