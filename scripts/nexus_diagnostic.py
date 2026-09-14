#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_diagnostics import DiagnosticInput, NexusDiagnosticAI


def main() -> None:
    parser = argparse.ArgumentParser(description="Run an evidence-grounded Nexus diagnosis.")
    parser.add_argument("input")
    parser.add_argument("--output")
    args = parser.parse_args()
    value = DiagnosticInput(**json.loads(Path(args.input).read_text(encoding="utf-8")))
    report = asdict(NexusDiagnosticAI().diagnose(value))
    text = json.dumps(report, ensure_ascii=True, indent=2)
    if args.output:
        Path(args.output).write_text(text + "\n", encoding="utf-8")
    print(text)


if __name__ == "__main__":
    main()
