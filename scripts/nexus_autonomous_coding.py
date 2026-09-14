#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from dataclasses import asdict
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_autonomous import AutonomousCoding, CodingPlan, FileOperation


def main() -> None:
    parser = argparse.ArgumentParser(description="Execute a permission-gated Nexus coding plan.")
    parser.add_argument("workspace")
    parser.add_argument("plan")
    parser.add_argument("--permissions", nargs="*", default=[])
    parser.add_argument("--prepare-commit", action="store_true")
    parser.add_argument("--prepare-pull-request", action="store_true")
    args = parser.parse_args()
    value = json.loads(Path(args.plan).read_text(encoding="utf-8"))
    plan = CodingPlan(
        value["objective"], value["analysis"], value["understanding"],
        tuple(value["steps"]), tuple(FileOperation(**item) for item in value["operations"]),
        tuple(value["tests"]), value["expected_result"],
    )
    result = AutonomousCoding(args.workspace).execute(
        plan,
        set(args.permissions),
        prepare_commit=args.prepare_commit,
        prepare_pull_request=args.prepare_pull_request,
    )
    print(json.dumps(asdict(result), ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
