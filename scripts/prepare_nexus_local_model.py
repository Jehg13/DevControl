#!/usr/bin/env python3
"""Prepare and train the dependency-free Nexus local language model.

The corpus is intentionally restricted to repository-local JSONL knowledge.
No network access or remote model is used.
"""

from __future__ import annotations

import argparse
import hashlib
import hmac
import json
import os
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_tokenizer import NexusTokenizer
from nexus_training import TrainingConfig, train


def collect_corpus(root: Path) -> list[str]:
    texts: list[str] = []
    for path in sorted(root.rglob("*.jsonl")):
        for line in path.read_text(encoding="utf-8").splitlines():
            if line.strip():
                texts.append(line.strip())
    if not texts:
        raise ValueError(f"No hay datos JSONL locales en {root}.")
    return texts


def dataset_hash(dataset: Path) -> str:
    digest = hashlib.sha256()
    for path in sorted(dataset.glob("*.jsonl")):
        digest.update(path.name.encode())
        digest.update(path.read_bytes())
    return digest.hexdigest()


def write_dataset(texts: list[str], tokenizer: NexusTokenizer, output: Path) -> dict[str, int]:
    output.mkdir(parents=True, exist_ok=True)
    handles = {
        "training": (output / "training-local.jsonl").open("w", encoding="utf-8", newline="\n"),
        "validation": (output / "validation-local.jsonl").open("w", encoding="utf-8", newline="\n"),
        "test": (output / "test-local.jsonl").open("w", encoding="utf-8", newline="\n"),
    }
    counts = {key: 0 for key in handles}
    try:
        for index, text in enumerate(texts):
            remainder = index % 10
            split = "test" if remainder == 9 else "validation" if remainder == 8 else "training"
            record = {
                "id": f"local-{index:06d}",
                "source": "repository-local-jsonl",
                "input_ids": tokenizer.encode(text, add_bos=True, add_eos=True),
            }
            handles[split].write(json.dumps(record, ensure_ascii=True) + "\n")
            counts[split] += 1
    finally:
        for handle in handles.values():
            handle.close()
    if min(counts.values()) < 1:
        raise ValueError(f"La división local no produjo train/validation/test completos: {counts}")
    return counts


def approve(dataset: Path, approver: str) -> None:
    key = os.environ.get("NEXUS_DATASET_APPROVAL_KEY")
    if not key:
        raise ValueError("Define NEXUS_DATASET_APPROVAL_KEY para aprobar el dataset local.")
    digest = dataset_hash(dataset)
    signature = hmac.new(
        key.encode(),
        f"{digest}:{approver}".encode(),
        hashlib.sha256,
    ).hexdigest()
    (dataset / "approval.json").write_text(json.dumps({
        "format": "nexus-dataset-approval-v1",
        "dataset_sha256": digest,
        "approved_by": approver,
        "signature": signature,
    }, indent=2) + "\n", encoding="utf-8")


def main() -> int:
    parser = argparse.ArgumentParser(description="Train Nexus only from local repository datasets.")
    parser.add_argument("--corpus", default="nexus_ai/fundamentals")
    parser.add_argument("--output", default="storage/app/nexus-model")
    parser.add_argument("--vocab-size", type=int, default=512)
    parser.add_argument("--hidden-size", type=int, default=16)
    parser.add_argument("--epochs", type=int, default=3)
    parser.add_argument("--seed", type=int, default=1337)
    parser.add_argument("--approver", default="local-repository-owner")
    args = parser.parse_args()

    corpus = Path(args.corpus)
    output = Path(args.output)
    texts = collect_corpus(corpus)
    tokenizer = NexusTokenizer.train(texts, vocab_size=args.vocab_size, min_frequency=1)
    output.mkdir(parents=True, exist_ok=True)
    tokenizer.save(output / "tokenizer.json", [
        hashlib.sha256(text.encode("utf-8")).hexdigest() for text in texts
    ])
    counts = write_dataset(texts, tokenizer, output)
    approve(output, args.approver)
    summary = train(
        output,
        output,
        tokenizer.vocab_size,
        TrainingConfig(
            seed=args.seed,
            hidden_size=args.hidden_size,
            epochs=args.epochs,
            eval_interval=1,
            checkpoint_interval=max(1, len(texts)),
        ),
        require_approval=True,
    )
    metadata = {
        "format": "nexus-local-model-metadata-v1",
        "architecture": "nexus-micro-causal-v1",
        "checkpoint_format": "nexus-micro-checkpoint-v1",
        "tokenizer_format": "nexus-byte-bpe",
        "corpus": str(corpus),
        "examples": len(texts),
        "splits": counts,
        "tokenizer_vocab_size": tokenizer.vocab_size,
        "training": summary,
        "dataset_tokens": summary.get("dataset_tokens", {}),
        "validation_loss": summary.get("final_validation_loss"),
        "test_loss": summary.get("final_test_loss"),
    }
    (output / "metadata.json").write_text(
        json.dumps(metadata, ensure_ascii=True, indent=2) + "\n",
        encoding="utf-8",
    )
    print(json.dumps(metadata, ensure_ascii=True, indent=2))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
