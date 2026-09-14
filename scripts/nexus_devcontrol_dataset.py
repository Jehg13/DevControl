#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_devcontrol import DevControlSpecialization
from nexus_tokenizer import NexusTokenizer


def main() -> None:
    parser = argparse.ArgumentParser(description="Build the Nexus DevControl specialization dataset.")
    parser.add_argument("examples")
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--output", required=True)
    args = parser.parse_args()
    specialization = DevControlSpecialization(NexusTokenizer.load(args.tokenizer))
    stats = specialization.build(specialization.read_jsonl(args.examples), args.output)
    print(json.dumps(stats, ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
