import json
import tempfile
import unittest
from pathlib import Path

from nexus_dataset import DatasetBuilder, DatasetConfig, SourceRecord
from nexus_tokenizer import NexusTokenizer


class NexusDatasetTest(unittest.TestCase):
    def setUp(self):
        tokenizer = NexusTokenizer.train(["<?php class UserService {}", "SELECT * FROM users;"], vocab_size=320, min_frequency=1)
        self.builder = DatasetBuilder(tokenizer, DatasetConfig(shard_size=1))

    def test_filters_unauthorized_secrets_and_duplicates_and_writes_shards(self):
        records = [
            SourceRecord("code-1", "<?php\nclass UserService { public function find() {} }\n", "MIT", "repository", path="app/UserService.php"),
            SourceRecord("duplicate", "<?php\nclass UserService { public function find() {} }\n", "MIT", "repository", path="copy.php"),
            SourceRecord("private", "authorized private text that is not public", "MIT", "repository", private=True),
            SourceRecord("secret", "password = 'supersecretvalue'", "MIT", "repository", path=".env"),
            SourceRecord("unknown-license", "This is a sufficiently long technical document.", "Unknown", "web"),
        ]
        with tempfile.TemporaryDirectory() as directory:
            stats = self.builder.build(records, directory)
            self.assertEqual(stats["counts"]["accepted"], 1)
            self.assertEqual(stats["counts"]["rejected_duplicate"], 1)
            self.assertEqual(stats["counts"]["rejected_secret"], 1)
            shard = next(Path(directory).glob("training-*.jsonl"))
            example = json.loads(shard.read_text(encoding="utf-8"))
            self.assertEqual(example["category"], "code")
            self.assertGreater(example["token_count"], 0)

    def test_split_is_deterministic(self):
        record = SourceRecord("doc", "A technical explanation of deterministic systems and APIs.", "CC-BY-4.0", "documentation", path="README.md")
        with tempfile.TemporaryDirectory() as first, tempfile.TemporaryDirectory() as second:
            left = self.builder.build([record], first)
            right = self.builder.build([record], second)
            self.assertEqual(left["manifest_sha256"], right["manifest_sha256"])


if __name__ == "__main__":
    unittest.main()
