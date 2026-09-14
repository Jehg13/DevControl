#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_proactive import DetectionInput, ProactiveDetector


def main() -> None:
    parser = argparse.ArgumentParser(description="Detect proactive DevControl alerts.")
    parser.add_argument("input")
    parser.add_argument("--output", required=True)
    parser.add_argument("--min-confidence", type=float, default=0.45)
    args = parser.parse_args()
    value = DetectionInput(**json.loads(Path(args.input).read_text(encoding="utf-8")))
    alerts = ProactiveDetector().detect(value, args.min_confidence)
    Path(args.output).write_text(
        json.dumps([alert.to_devcontrol() for alert in alerts], ensure_ascii=True, indent=2) + "\n",
        encoding="utf-8",
    )
    print(json.dumps({"project": value.project, "alerts": len(alerts)}, indent=2))


if __name__ == "__main__":
    main()
