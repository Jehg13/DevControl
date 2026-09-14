#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_tokenizer import NexusTokenizer
from nexus_tool_calling import ToolCallingDataset


def main() -> None:
    parser = argparse.ArgumentParser(description="Build the Nexus tool-calling dataset.")
    parser.add_argument("examples")
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    builder = ToolCallingDataset(NexusTokenizer.load(args.tokenizer))
    stats = builder.build(builder.read_jsonl(args.examples), args.output)
    print(json.dumps(stats, indent=2))


if __name__ == "__main__":
    main()
