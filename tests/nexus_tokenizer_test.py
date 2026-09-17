import tempfile
import unittest
from pathlib import Path

from nexus_tokenizer import NexusTokenizer


class NexusTokenizerTest(unittest.TestCase):
    def setUp(self):
        self.texts = [
            "<?php\nnamespace App\\Services;\nclass UserService { public function findUser(int $id): ?User {} }\n",
            "SELECT * FROM users WHERE id = ?;\n",
            "flutter pub get && dart test\n",
            "TypeError: Cannot read properties of undefined\\n at app.ts:42:7",
            '{"name": "DevControl", "enabled": true}\n',
        ]
        self.tokenizer = NexusTokenizer.train(self.texts, vocab_size=512, min_frequency=1)

    def test_round_trip_preserves_code_and_unicode(self):
        value = self.texts[0] + "ñ 日本語"
        ids = self.tokenizer.encode(value)
        self.assertEqual(self.tokenizer.decode(ids), value)

    def test_special_tokens_and_padding(self):
        value = "<|system|> analyze routes"
        ids = self.tokenizer.encode(value, max_length=12, padding=True)
        self.assertEqual(self.tokenizer.decode(ids), value + "<|pad|>" * (12 - len(self.tokenizer.encode(value))))
        self.assertEqual(ids[0], self.tokenizer._special_to_id["<|system|>"])

    def test_truncation_is_explicit(self):
        with self.assertRaises(ValueError):
            self.tokenizer.encode("long source", max_length=2)
        self.assertEqual(len(self.tokenizer.encode("long source", max_length=2, truncation=True)), 2)

    def test_artifact_is_reproducible_and_verified(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "tokenizer.json"
            self.tokenizer.save(path, ["abc"])
            loaded = NexusTokenizer.load(path)
            self.assertEqual(loaded.encode(self.texts[1]), self.tokenizer.encode(self.texts[1]))
            self.assertEqual(loaded.context_window, self.tokenizer.context_window)
            path.write_text(path.read_text(encoding="utf-8").replace('"vocab_size":', '"vocab_size": 999, "ignored":'), encoding="utf-8")
            with self.assertRaises(ValueError):
                NexusTokenizer.load(path)

    def test_training_is_deterministic(self):
        first = NexusTokenizer.train(self.texts, vocab_size=512, min_frequency=1)
        second = NexusTokenizer.train(self.texts, vocab_size=512, min_frequency=1)
        self.assertEqual(first.to_dict(["corpus"]), second.to_dict(["corpus"]))

    def test_context_window_and_attention_mask_are_supported(self):
        ids, mask = self.tokenizer.encode_with_attention(self.texts[0], max_length=16, padding=True, truncation=True)
        self.assertEqual(len(ids), len(mask))
        self.assertEqual(len(ids), 16)
        self.assertEqual(mask.count(1), len(ids))


if __name__ == "__main__":
    unittest.main()
