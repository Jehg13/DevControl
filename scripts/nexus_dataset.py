#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_dataset import DatasetBuilder, DatasetConfig
from nexus_tokenizer import NexusTokenizer


def main() -> None:
    parser = argparse.ArgumentParser(description="Build a licensed Nexus AI pretraining dataset.")
    parser.add_argument("manifest", help="JSONL source manifest")
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--output", required=True)
    parser.add_argument("--shard-size", type=int, default=1000)
    args = parser.parse_args()
    if args.shard_size < 1:
        parser.error("--shard-size must be positive")
    tokenizer = NexusTokenizer.load(args.tokenizer)
    records = DatasetBuilder.read_manifest(args.manifest)
    stats = DatasetBuilder(tokenizer, DatasetConfig(shard_size=args.shard_size)).build(
        records, args.output
    )
    print(json.dumps(stats, ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
