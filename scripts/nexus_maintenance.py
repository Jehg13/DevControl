#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_maintenance import MaintenanceService


def main() -> None:
    parser = argparse.ArgumentParser(description="Run an auditable Nexus maintenance cycle.")
    parser.add_argument("input", help="JSON object with project and review findings")
    parser.add_argument("--output", required=True)
    parser.add_argument("--permissions", nargs="*", default=[])
    parser.add_argument("--approved-actions", nargs="*", default=[])
    args = parser.parse_args()
    payload = json.loads(Path(args.input).read_text(encoding="utf-8"))
    reviewers = {
        check: (lambda values: lambda: values)(values)
        for check, values in payload.get("reviews", {}).items()
    }
    run = MaintenanceService(args.output).run(
        payload["project"],
        reviewers,
        permissions=set(args.permissions),
        approved_actions=set(args.approved_actions),
    )
    print(json.dumps({
        "run_id": run.run_id,
        "findings": len(run.findings),
        "tasks": len(run.tasks),
        "actions": len(run.actions),
        "audit_file": run.audit_file,
    }, indent=2))


if __name__ == "__main__":
    main()
