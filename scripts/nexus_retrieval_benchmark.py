#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_context import ContextItem, ContextRetriever, evaluate_retrieval
from nexus_tokenizer import NexusTokenizer


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate Nexus context retrieval.")
    parser.add_argument("items")
    parser.add_argument("benchmark")
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--output")
    args = parser.parse_args()
    items = [
        ContextItem(**value)
        for value in json.loads(Path(args.items).read_text(encoding="utf-8"))
    ]
    report = evaluate_retrieval(
        ContextRetriever(NexusTokenizer.load(args.tokenizer), items),
        args.benchmark,
    )
    text = json.dumps(report, indent=2)
    if args.output:
        Path(args.output).write_text(text + "\n", encoding="utf-8")
    print(text)


if __name__ == "__main__":
    main()
