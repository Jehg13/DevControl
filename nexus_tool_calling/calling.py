from __future__ import annotations

import hashlib
import json
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any, Iterable

from nexus_tokenizer import NexusTokenizer


ALLOWED_TOOLS = {
    "search_file",
    "analyze_project",
    "query_devcontrol",
    "query_github",
    "read_logs",
    "run_analysis",
    "modify_file",
    "run_tests",
}
READ_ONLY_TOOLS = {
    "search_file",
    "analyze_project",
    "query_devcontrol",
    "query_github",
    "read_logs",
    "run_analysis",
    "run_tests",
}
REQUIRED_FIELDS = {"tool", "parameters", "reason", "expected_result"}


@dataclass(frozen=True)
class ToolCall:
    tool: str
    parameters: dict[str, Any]
    reason: str
    expected_result: str


@dataclass(frozen=True)
class ToolResult:
    tool: str
    status: str
    result: Any = None
    error: str | None = None
    permission_required: bool = False


class ToolCallValidator:
    """Validates model proposals; it never executes a tool."""

    def validate(self, value: Any, granted_permissions: set[str] | None = None) -> ToolResult | ToolCall:
        if not isinstance(value, dict):
            return ToolResult("", "invalid", error="tool call must be an object")
        missing = REQUIRED_FIELDS - set(value)
        if missing:
            return ToolResult("", "invalid", error=f"missing fields: {sorted(missing)}")
        tool = value["tool"]
        if not isinstance(tool, str) or tool not in ALLOWED_TOOLS:
            return ToolResult(str(tool), "invalid", error="unknown tool")
        if not isinstance(value["parameters"], dict):
            return ToolResult(tool, "invalid", error="parameters must be an object")
        if not isinstance(value["reason"], str) or not value["reason"].strip():
            return ToolResult(tool, "invalid", error="reason is required")
        if not isinstance(value["expected_result"], str) or not value["expected_result"].strip():
            return ToolResult(tool, "invalid", error="expected_result is required")
        permission = f"nexus.tool.{tool}"
        if granted_permissions is not None and permission not in granted_permissions:
            return ToolResult(
                tool,
                "permission_required",
                error=permission,
                permission_required=True,
            )
        return ToolCall(tool, value["parameters"], value["reason"], value["expected_result"])

    def authorize_and_execute(
        self,
        value: Any,
        granted_permissions: set[str],
        executor,
    ) -> ToolResult:
        validated = self.validate(value, granted_permissions)
        if isinstance(validated, ToolResult):
            return validated
        result = executor(validated.tool, validated.parameters)
        return ToolResult(validated.tool, "completed", result=result)


class ToolCallingDataset:
    def __init__(self, tokenizer: NexusTokenizer) -> None:
        self.tokenizer = tokenizer

    @staticmethod
    def read_jsonl(path: str | Path) -> list[dict[str, Any]]:
        records = []
        for line_number, line in enumerate(Path(path).read_text(encoding="utf-8").splitlines(), 1):
            if not line.strip():
                continue
            try:
                records.append(json.loads(line))
            except json.JSONDecodeError as error:
                raise ValueError(f"invalid tool example line {line_number}: {error}") from error
        return records

    def build(self, records: Iterable[dict[str, Any]], output: str | Path) -> dict:
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        accepted = []
        rejected = Counter()
        seen = set()
        validator = ToolCallValidator()
        for record in records:
            call = record.get("tool_call")
            validated = validator.validate(call)
            if isinstance(validated, ToolResult):
                rejected[validated.error or "invalid"] += 1
                continue
            if not record.get("prompt", "").strip():
                rejected["missing_prompt"] += 1
                continue
            digest = hashlib.sha256(
                json.dumps(
                    {"prompt": record["prompt"], "tool_call": asdict(validated)},
                    sort_keys=True,
                    ensure_ascii=True,
                ).encode()
            ).hexdigest()
            if digest in seen:
                rejected["duplicate"] += 1
                continue
            seen.add(digest)
            text = (
                f"<|user|>{record['prompt']}\n"
                f"<|tool_call|>{json.dumps(asdict(validated), ensure_ascii=True, sort_keys=True)}"
            )
            accepted.append({
                "id": record.get("id", digest[:12]),
                "tool": validated.tool,
                "prompt": record["prompt"],
                "tool_call": asdict(validated),
                "example_sha256": digest,
                "input_ids": self.tokenizer.encode(text, add_bos=True, add_eos=True),
            })
        path = output_path / "training-tool-calling.jsonl"
        with path.open("w", encoding="utf-8", newline="\n") as handle:
            for item in accepted:
                handle.write(json.dumps(item, sort_keys=True) + "\n")
        stats = {
            "format": "nexus-tool-calling-v1",
            "accepted": len(accepted),
            "rejected": dict(rejected),
            "tools": dict(Counter(item["tool"] for item in accepted)),
            "read_only_tools": sorted(READ_ONLY_TOOLS),
            "training_file": path.name,
        }
        (output_path / "tool-calling-stats.json").write_text(
            json.dumps(stats, indent=2) + "\n", encoding="utf-8"
        )
        return stats


def evaluate_tool_calls(benchmark_path: str | Path) -> dict:
    validator = ToolCallValidator()
    results = []
    for case in json.loads(Path(benchmark_path).read_text(encoding="utf-8")):
        value = validator.validate(case["call"], set(case.get("permissions", [])))
        status = value.status if isinstance(value, ToolResult) else "valid"
        results.append({
            "id": case["id"],
            "expected_status": case["expected_status"],
            "actual_status": status,
            "passed": status == case["expected_status"],
        })
    return {
        "cases": len(results),
        "passed": sum(item["passed"] for item in results),
        "accuracy": sum(item["passed"] for item in results) / len(results) if results else 0.0,
        "results": results,
    }
