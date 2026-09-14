#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_inference import InferenceConfig, LocalInferenceRuntime
from nexus_inference.runtime import InferenceCancelled, request_from_payload


def main() -> int:
    parser = argparse.ArgumentParser(description="Offline Nexus inference runtime.")
    parser.add_argument("--checkpoint", required=True)
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--max-context-tokens", type=int, default=2048)
    parser.add_argument("--max-new-tokens", type=int, default=128)
    parser.add_argument("--temperature", type=float, default=0.7)
    parser.add_argument("--top-k", type=int, default=40)
    parser.add_argument("--top-p", type=float, default=0.9)
    parser.add_argument("--timeout", type=float, default=30)
    parser.add_argument("--logits-cache-size", type=int, default=1024)
    parser.add_argument("--quantization", choices=("none", "int8"), default="none")
    args = parser.parse_args()
    runtime = LocalInferenceRuntime(
        args.checkpoint,
        args.tokenizer,
        InferenceConfig(
            max_context_tokens=args.max_context_tokens,
            max_new_tokens=args.max_new_tokens,
            temperature=args.temperature,
            top_k=args.top_k,
            top_p=args.top_p,
            timeout_seconds=args.timeout,
            logits_cache_size=args.logits_cache_size,
            quantization=args.quantization,
        ),
    )
    for line in sys.stdin:
        if not line.strip():
            continue
        try:
            payload = json.loads(line)
            request_from_payload(runtime=runtime, payload=payload, emit=lambda event: print(json.dumps(event, ensure_ascii=True), flush=True))
        except (InferenceCancelled, TimeoutError, ValueError, OSError, json.JSONDecodeError) as error:
            print(json.dumps({"event": "error", "error": str(error)}), flush=True)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
