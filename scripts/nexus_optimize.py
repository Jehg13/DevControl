#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import statistics
import sys
import time
import tracemalloc
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_inference import InferenceConfig, LocalInferenceRuntime


def benchmark(checkpoint: str, tokenizer: str, prompts: list[str], quantization: str, repeats: int) -> dict:
    runtime = LocalInferenceRuntime(
        checkpoint,
        tokenizer,
        InferenceConfig(max_new_tokens=32, temperature=0, quantization=quantization),
    )
    durations = []
    outputs = []
    tracemalloc.start()
    for _ in range(repeats):
        for prompt in prompts:
            started = time.perf_counter()
            result = runtime.generate(prompt)
            durations.append(time.perf_counter() - started)
            outputs.append(result["text"])
    peak = tracemalloc.get_traced_memory()[1]
    tracemalloc.stop()
    return {
        "quantization": quantization,
        "runs": len(durations),
        "latency_ms_avg": round(statistics.mean(durations) * 1000, 3),
        "latency_ms_p95": round(sorted(durations)[max(0, int(len(durations) * 0.95) - 1)] * 1000, 3),
        "peak_python_bytes": peak,
        "estimated_model_bytes": runtime.estimated_model_bytes(),
        "outputs": outputs,
        "cache_hits": runtime._cache_hits,
        "cache_misses": runtime._cache_misses,
    }


def main() -> int:
    parser = argparse.ArgumentParser(description="Compare safe local Nexus inference optimizations.")
    parser.add_argument("--checkpoint", required=True)
    parser.add_argument("--tokenizer", required=True)
    parser.add_argument("--prompt", action="append", required=True)
    parser.add_argument("--repeats", type=int, default=3)
    parser.add_argument("--output")
    args = parser.parse_args()
    if args.repeats < 1:
        parser.error("--repeats must be positive")
    baseline = benchmark(args.checkpoint, args.tokenizer, args.prompt, "none", args.repeats)
    quantized = benchmark(args.checkpoint, args.tokenizer, args.prompt, "int8", args.repeats)
    matching = sum(a == b for a, b in zip(baseline["outputs"], quantized["outputs"]))
    result = {
        "format": "nexus-optimization-report-v1",
        "original_checkpoint_preserved": True,
        "baseline": baseline,
        "int8_candidate": quantized,
        "quality": {
            "exact_output_match_rate": matching / len(baseline["outputs"]) if baseline["outputs"] else 1,
            "accepted_by_default": matching == len(baseline["outputs"]),
        },
        "notes": [
            "KV cache is not applicable: the current micro-model depends only on the last token.",
            "GPU and compiler acceleration are not enabled because this runtime has no required backend dependency.",
            "The int8 candidate is reported, never replaces the original checkpoint automatically.",
        ],
    }
    encoded = json.dumps(result, indent=2, ensure_ascii=True)
    print(encoded)
    if args.output:
        Path(args.output).write_text(encoded + "\n", encoding="utf-8")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
