from __future__ import annotations

import hashlib
import json
import re
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable

from nexus_tokenizer import NexusTokenizer


DATASET_VERSION = "1.0.0"
ALLOWED_LICENSES = {
    "MIT", "Apache-2.0", "BSD-2-Clause", "BSD-3-Clause", "ISC",
    "CC0-1.0", "CC-BY-4.0", "CC-BY-3.0", "Unlicense", "Public-Domain",
    "proprietary-authorized",
}
SECRET_PATTERNS = (
    r"-----BEGIN [A-Z ]*PRIVATE KEY-----",
    r"(?i)\b(?:api[_-]?key|secret|password|passwd|token)\s*[:=]\s*['\"]?[A-Za-z0-9_\-./+=]{8,}",
    r"(?i)\b(?:aws_access_key_id|aws_secret_access_key)\s*[:=]",
    r"\bgh[pousr]_[A-Za-z0-9_]{20,}\b",
    r"\bsk-[A-Za-z0-9]{20,}\b",
)
MALWARE_PATTERNS = (
    r"(?i)\b(?:ransomware|keylogger|credential\s+stealer|reverse\s+shell)\b",
    r"(?i)\b(?:invoke-webrequest|curl)\b.{0,120}\|\s*(?:iex|bash|sh)\b",
)
TEXT_EXTENSIONS = {
    ".php", ".inc", ".dart", ".js", ".jsx", ".ts", ".tsx", ".sql", ".html",
    ".htm", ".css", ".scss", ".json", ".yaml", ".yml", ".md", ".markdown",
    ".txt", ".log", ".xml", ".sh", ".bash", ".zsh", ".ps1", ".bat", ".cmd",
    ".env.example", ".gitignore", ".dockerfile",
}


@dataclass(frozen=True)
class SourceRecord:
    source_id: str
    text: str
    license: str
    source_type: str
    permitted: bool = True
    private: bool = False
    url: str | None = None
    path: str | None = None
    metadata: dict[str, str] | None = None


@dataclass(frozen=True)
class DatasetConfig:
    dataset_version: str = DATASET_VERSION
    validation_ratio: float = 0.02
    test_ratio: float = 0.02
    shard_size: int = 1000
    min_characters: int = 24
    max_characters: int = 2_000_000
    max_repeated_line_ratio: float = 0.8
    min_quality: float = 0.35


class DatasetBuilder:
    def __init__(self, tokenizer: NexusTokenizer, config: DatasetConfig | None = None) -> None:
        self.tokenizer = tokenizer
        self.config = config or DatasetConfig()

    @staticmethod
    def read_manifest(path: str | Path) -> list[SourceRecord]:
        records: list[SourceRecord] = []
        for line_number, line in enumerate(Path(path).read_text(encoding="utf-8").splitlines(), 1):
            if not line.strip():
                continue
            try:
                value = json.loads(line)
                records.append(SourceRecord(**value))
            except (TypeError, ValueError, KeyError) as error:
                raise ValueError(f"invalid source manifest line {line_number}: {error}") from error
        return records

    @staticmethod
    def _matches(patterns: Iterable[str], text: str) -> bool:
        return any(re.search(pattern, text) for pattern in patterns)

    def _quality(self, text: str) -> float:
        lines = [line.strip() for line in text.splitlines() if line.strip()]
        if not lines:
            return 0.0
        unique_ratio = len(set(lines)) / len(lines)
        replacement_ratio = text.count("\ufffd") / max(len(text), 1)
        printable_ratio = sum(character.isprintable() or character in "\n\r\t" for character in text) / len(text)
        return max(0.0, min(1.0, (unique_ratio + (1 - replacement_ratio) + printable_ratio) / 3))

    def _category(self, record: SourceRecord) -> str:
        extension = Path(record.path or "").suffix.lower()
        if extension in {".php", ".inc", ".dart", ".js", ".jsx", ".ts", ".tsx"}:
            return "code"
        if extension in {".sql"}:
            return "database"
        if extension in {".md", ".markdown", ".txt"}:
            return "documentation"
        if extension in {".log"}:
            return "logs"
        if extension in {".sh", ".bash", ".zsh", ".ps1", ".bat", ".cmd"}:
            return "terminal"
        if extension in {".json", ".yaml", ".yml", ".xml"}:
            return "configuration"
        if extension in {".html", ".htm", ".css", ".scss"}:
            return "web"
        return record.source_type or "technical"

    def _split(self, digest: str) -> str:
        value = int(digest[:8], 16) / 0xFFFFFFFF
        if value < self.config.test_ratio:
            return "test"
        if value < self.config.test_ratio + self.config.validation_ratio:
            return "validation"
        return "training"

    def build(self, records: Iterable[SourceRecord], output: str | Path) -> dict:
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        counters = Counter()
        seen: set[str] = set()
        accepted: list[dict] = []

        for record in records:
            counters["ingested"] += 1
            if not record.permitted or record.private:
                counters["rejected_unauthorized"] += 1
                continue
            if record.license not in ALLOWED_LICENSES:
                counters["rejected_license"] += 1
                continue
            text = record.text.replace("\r\n", "\n").replace("\r", "\n").strip()
            if not (self.config.min_characters <= len(text) <= self.config.max_characters):
                counters["rejected_length"] += 1
                continue
            if self._matches(SECRET_PATTERNS, text):
                counters["rejected_secret"] += 1
                continue
            if self._matches(MALWARE_PATTERNS, text):
                counters["rejected_malware"] += 1
                continue
            quality = self._quality(text)
            if quality < self.config.min_quality:
                counters["rejected_quality"] += 1
                continue
            digest = hashlib.sha256(" ".join(text.split()).encode("utf-8")).hexdigest()
            if digest in seen:
                counters["rejected_duplicate"] += 1
                continue
            seen.add(digest)
            token_ids = self.tokenizer.encode(text, add_bos=True, add_eos=True)
            split = self._split(digest)
            accepted.append({
                "id": record.source_id,
                "text_sha256": hashlib.sha256(text.encode("utf-8")).hexdigest(),
                "dedup_sha256": digest,
                "license": record.license,
                "source_type": record.source_type,
                "category": self._category(record),
                "split": split,
                "quality": round(quality, 6),
                "token_count": len(token_ids),
                "input_ids": token_ids,
                "metadata": record.metadata or {},
            })
            counters["accepted"] += 1
            counters[f"accepted_{split}"] += 1

        shards: dict[str, list[dict]] = {"training": [], "validation": [], "test": []}
        shard_files: list[str] = []
        for example in accepted:
            split = example["split"]
            shards[split].append(example)
        for split, examples in shards.items():
            for index in range(0, len(examples), self.config.shard_size):
                shard_number = index // self.config.shard_size
                filename = f"{split}-{shard_number:05d}.jsonl"
                path = output_path / filename
                with path.open("w", encoding="utf-8", newline="\n") as handle:
                    for example in examples[index:index + self.config.shard_size]:
                        handle.write(json.dumps(example, ensure_ascii=True, sort_keys=True) + "\n")
                if examples[index:index + self.config.shard_size]:
                    shard_files.append(filename)

        stats = {
            "dataset_version": self.config.dataset_version,
            "tokenizer_vocab_size": self.tokenizer.vocab_size,
            "counts": dict(counters),
            "tokens": sum(example["token_count"] for example in accepted),
            "categories": dict(Counter(example["category"] for example in accepted)),
            "licenses": dict(Counter(example["license"] for example in accepted)),
            "shards": shard_files,
        }
        stats["manifest_sha256"] = hashlib.sha256(
            json.dumps(stats, sort_keys=True, separators=(",", ":")).encode()
        ).hexdigest()
        (output_path / "dataset-stats.json").write_text(
            json.dumps(stats, ensure_ascii=True, indent=2) + "\n", encoding="utf-8"
        )
        return stats
