from __future__ import annotations

import json
import os
import platform
import time
from dataclasses import dataclass
from pathlib import Path
from typing import Callable, Iterable

from nexus_inference import InferenceConfig, LocalInferenceRuntime


class RuntimeErrorBase(Exception):
    """Controlled failure from the standalone runtime."""


@dataclass(frozen=True)
class RuntimeVersion:
    version: str
    checkpoint: Path
    tokenizer: Path
    max_context_tokens: int
    max_new_tokens: int
    temperature: float
    top_k: int
    top_p: float
    timeout_seconds: float
    logits_cache_size: int
    quantization: str
    tools: tuple[str, ...]


class NexusRuntime:
    """Owns model, tokenizer, context, memory, inference and telemetry."""

    def __init__(self, manifest_path: str | Path, version: str) -> None:
        self.manifest_path = Path(manifest_path)
        self.manifest = self._read_manifest()
        self.version = version
        self.spec = self._resolve_version(version)
        self._runtime = LocalInferenceRuntime(
            self.spec.checkpoint,
            self.spec.tokenizer,
            InferenceConfig(
                max_context_tokens=self.spec.max_context_tokens,
                max_new_tokens=self.spec.max_new_tokens,
                temperature=self.spec.temperature,
                top_k=self.spec.top_k,
                top_p=self.spec.top_p,
                timeout_seconds=self.spec.timeout_seconds,
                logits_cache_size=self.spec.logits_cache_size,
                quantization=self.spec.quantization,
            ),
        )
        self.memory: list[dict[str, str]] = []

    def health(self) -> dict:
        return {
            "healthy": self.spec.checkpoint.is_file() and self.spec.tokenizer.is_file(),
            "version": self.version,
            "model": str(self.spec.checkpoint),
            "tokenizer": str(self.spec.tokenizer),
            "hardware": self.hardware(),
            "capabilities": {
                "inference": True,
                "streaming": True,
                "tool_calling": bool(self.spec.tools),
                "memory": True,
                "context": True,
            },
        }

    def hardware(self) -> dict:
        try:
            import resource
            max_rss = resource.getrusage(resource.RUSAGE_SELF).ru_maxrss * (1024 if os.name != "nt" else 1)
        except ImportError:
            max_rss = 0
        return {
            "platform": platform.platform(),
            "python": platform.python_version(),
            "cpu_count": os.cpu_count(),
            "max_rss_bytes": max_rss,
        }

    def infer(
        self,
        prompt: str,
        *,
        context: Iterable[str] = (),
        tool_calls: Iterable[dict] = (),
        on_event: Callable[[dict], None] | None = None,
    ) -> dict:
        started = time.perf_counter()
        requested_tools = list(tool_calls)
        self._validate_tool_calls(requested_tools)
        context_items = list(context)
        memory_context = [item["content"] for item in self.memory[-8:]]
        composed = "\n".join([*context_items, *memory_context, prompt])
        events: list[dict] = []

        def emit(event: dict) -> None:
            events.append(event)
            if on_event:
                on_event(event)

        result = self._runtime.generate(
            composed,
            on_token=lambda token: emit({"event": "token", "token": token}),
        )
        self.memory.extend([
            {"role": "user", "content": prompt},
            {"role": "assistant", "content": result["text"]},
        ])
        metrics = {
            **result,
            "version": self.version,
            "duration_ms": round((time.perf_counter() - started) * 1000, 3),
            "memory_items": len(self.memory),
            "hardware": self.hardware(),
            "tool_calls": requested_tools,
            "errors": [],
        }
        complete = {"event": "complete", "result": result["text"], "metrics": metrics}
        emit(complete)
        return {"version": self.version, "result": result["text"], "metrics": metrics, "events": events}

    def _validate_tool_calls(self, calls: Iterable[dict]) -> None:
        for call in calls:
            if not isinstance(call, dict) or not isinstance(call.get("name"), str):
                raise RuntimeErrorBase("invalid_tool_call")
            if call["name"] not in self.spec.tools:
                raise RuntimeErrorBase("tool_not_allowed")
            if not isinstance(call.get("arguments", {}), dict):
                raise RuntimeErrorBase("invalid_tool_arguments")

    def _read_manifest(self) -> dict:
        try:
            payload = json.loads(self.manifest_path.read_text(encoding="utf-8"))
        except (OSError, ValueError) as error:
            raise RuntimeErrorBase("runtime_manifest_invalid") from error
        if payload.get("format") != "nexus-runtime-manifest-v1":
            raise RuntimeErrorBase("runtime_manifest_unsupported")
        return payload

    def _resolve_version(self, version: str) -> RuntimeVersion:
        values = self.manifest.get("versions", {})
        entry = values.get(version)
        if not isinstance(entry, dict):
            raise RuntimeErrorBase(f"runtime_version_not_found:{version}")
        root = self.manifest_path.parent.parent
        required = ["checkpoint", "tokenizer"]
        if any(not isinstance(entry.get(key), str) for key in required):
            raise RuntimeErrorBase("runtime_version_artifact_invalid")
        root_path = (root / ".").resolve()
        checkpoint = (root / entry["checkpoint"]).resolve()
        tokenizer = (root / entry["tokenizer"]).resolve()
        if not str(checkpoint).startswith(str(root_path)) or not str(tokenizer).startswith(str(root_path)):
            raise RuntimeErrorBase("runtime_artifact_outside_project")
        return RuntimeVersion(
            version=version,
            checkpoint=checkpoint,
            tokenizer=tokenizer,
            max_context_tokens=int(entry.get("max_context_tokens", 2048)),
            max_new_tokens=int(entry.get("max_new_tokens", 128)),
            temperature=float(entry.get("temperature", 0.7)),
            top_k=int(entry.get("top_k", 40)),
            top_p=float(entry.get("top_p", 0.9)),
            timeout_seconds=float(entry.get("timeout_seconds", 30)),
            logits_cache_size=int(entry.get("logits_cache_size", 1024)),
            quantization=str(entry.get("quantization", "none")),
            tools=tuple(entry.get("tools", [])),
        )
