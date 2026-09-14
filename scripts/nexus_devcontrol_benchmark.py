#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_devcontrol import evaluate_devcontrol
from nexus_tokenizer import NexusTokenizer
from nexus_training import load_checkpoint


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate evidence grounding in Nexus DevControl.")
    parser.add_argument("checkpoint")
    parser.add_argument("tokenizer")
    parser.add_argument("benchmark")
    parser.add_argument("--output")
    args = parser.parse_args()
    report = evaluate_devcontrol(
        load_checkpoint(args.checkpoint),
        NexusTokenizer.load(args.tokenizer),
        args.benchmark,
    )
    serialized = json.dumps(report, ensure_ascii=True, indent=2)
    if args.output:
        Path(args.output).write_text(serialized + "\n", encoding="utf-8")
    print(serialized)


if __name__ == "__main__":
    main()
