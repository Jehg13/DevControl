#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_runtime import NexusRuntime, RuntimeErrorBase


def main() -> int:
    parser = argparse.ArgumentParser(description="Standalone versioned Nexus AI runtime.")
    parser.add_argument("--manifest", default="config/nexus-runtime.json")
    parser.add_argument("--version", required=True)
    parser.add_argument("--health", action="store_true")
    args = parser.parse_args()
    try:
        runtime = NexusRuntime(args.manifest, args.version)
        if args.health:
            print(json.dumps(runtime.health(), ensure_ascii=True), flush=True)
            return 0
        for line in sys.stdin:
            if not line.strip():
                continue
            payload = json.loads(line)
            result = runtime.infer(
                payload["prompt"],
                context=payload.get("context", []),
                tool_calls=payload.get("tool_calls", []),
                on_event=lambda event: print(json.dumps(event, ensure_ascii=True), flush=True),
            )
            print(json.dumps({"event": "runtime_complete", "runtime": result}, ensure_ascii=True), flush=True)
        return 0
    except (RuntimeErrorBase, ValueError, KeyError, OSError, json.JSONDecodeError) as error:
        print(json.dumps({"event": "error", "error": str(error)}), file=sys.stderr, flush=True)
        return 2


if __name__ == "__main__":
    raise SystemExit(main())
