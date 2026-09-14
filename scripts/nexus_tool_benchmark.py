#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_tool_calling import evaluate_tool_calls


def main() -> None:
    parser = argparse.ArgumentParser(description="Evaluate Nexus tool-call validation.")
    parser.add_argument("benchmark")
    parser.add_argument("--output")
    args = parser.parse_args()
    report = evaluate_tool_calls(args.benchmark)
    text = json.dumps(report, indent=2)
    if args.output:
        Path(args.output).write_text(text + "\n", encoding="utf-8")
    print(text)


if __name__ == "__main__":
    main()
