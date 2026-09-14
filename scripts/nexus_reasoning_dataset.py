#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_reasoning import TechnicalReasoning
from nexus_tokenizer import NexusTokenizer


def main() -> None:
    parser = argparse.ArgumentParser(description="Build the Nexus technical reasoning dataset.")
    parser.add_argument("examples")
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    builder = TechnicalReasoning(NexusTokenizer.load(args.tokenizer))
    print(json.dumps(builder.build(builder.read_jsonl(args.examples), args.output), indent=2))


if __name__ == "__main__":
    main()
