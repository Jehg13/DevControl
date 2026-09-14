from __future__ import annotations

import hashlib
import json
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


TASKS = {
    "analyze_project",
    "find_problems",
    "explain_incident",
    "propose_solution",
    "change_impact",
    "prioritize_incidents",
    "probable_cause",
    "repair_plan",
    "missing_information",
}
DOMAINS = {
    "projects",
    "tasks",
    "bugs",
    "incidents",
    "analysis",
    "repositories",
    "changes",
    "deployments",
    "infrastructure",
    "history",
    "maintenance",
    "risks",
}
ABSTENTION = "No tengo evidencia suficiente."


@dataclass(frozen=True)
class DevControlExample:
    example_id: str
    task: str
    context: str
    evidence: tuple[str, ...]
    response: str
    expected_status: str
    domains: tuple[str, ...] = ()
    license: str = "proprietary-authorized"
    permitted: bool = True
    metadata: dict[str, str] | None = None


class DevControlSpecialization:
    def __init__(self, tokenizer: NexusTokenizer) -> None:
        self.tokenizer = tokenizer

    @staticmethod
    def read_jsonl(path: str | Path) -> list[DevControlExample]:
        examples: list[DevControlExample] = []
        for line_number, line in enumerate(Path(path).read_text(encoding="utf-8").splitlines(), 1):
            if not line.strip():
                continue
            try:
                value = json.loads(line)
                value["evidence"] = tuple(value.get("evidence", ()))
                value["domains"] = tuple(value.get("domains", ()))
                examples.append(DevControlExample(**value))
            except (TypeError, ValueError, KeyError) as error:
                raise ValueError(f"invalid DevControl example line {line_number}: {error}") from error
        return examples

    @staticmethod
    def _is_abstention(response: str) -> bool:
        normalized = response.casefold()
        return ABSTENTION.casefold() in normalized or "evidencia insuficiente" in normalized

    def build(self, examples: Iterable[DevControlExample], output: str | Path) -> dict:
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        accepted: list[dict] = []
        seen: set[str] = set()
        rejected = Counter()

        for example in examples:
            if example.task not in TASKS:
                rejected["unsupported_task"] += 1
                continue
            invalid_domains = set(example.domains) - DOMAINS
            if invalid_domains:
                rejected["unsupported_domain"] += 1
                continue
            if not example.permitted or not example.license:
                rejected["unauthorized"] += 1
                continue
            if not example.context.strip() or not example.response.strip():
                rejected["empty"] += 1
                continue
            if example.expected_status not in {"supported", "insufficient_evidence"}:
                rejected["invalid_status"] += 1
                continue
            abstains = self._is_abstention(example.response)
            if example.expected_status == "insufficient_evidence" and not abstains:
                rejected["missing_abstention"] += 1
                continue
            if example.expected_status == "supported" and abstains:
                rejected["unexpected_abstention"] += 1
                continue
            normalized = "\n".join([
                example.task,
                example.context,
                *example.evidence,
                example.response,
            ])
            digest = hashlib.sha256(normalized.encode("utf-8")).hexdigest()
            if digest in seen:
                rejected["duplicate"] += 1
                continue
            seen.add(digest)
            evidence_block = "\n".join(f"[EVIDENCE] {item}" for item in example.evidence)
            text = (
                f"<|user|>DevControl task={example.task}\n"
                f"{example.context}\n{evidence_block}\n"
                f"<|assistant|>{example.response}"
            )
            accepted.append({
                "id": example.example_id,
                "task": example.task,
                "domains": list(example.domains),
                "expected_status": example.expected_status,
                "evidence_count": len(example.evidence),
                "example_sha256": digest,
                "license": example.license,
                "input_ids": self.tokenizer.encode(text, add_bos=True, add_eos=True),
                "metadata": example.metadata or {},
            })

        path = output_path / "training-devcontrol.jsonl"
        with path.open("w", encoding="utf-8", newline="\n") as handle:
            for item in accepted:
                handle.write(json.dumps(item, sort_keys=True) + "\n")
        stats = {
            "format": "nexus-devcontrol-specialization-v1",
            "accepted": len(accepted),
            "rejected": dict(rejected),
            "tasks": dict(Counter(item["task"] for item in accepted)),
            "statuses": dict(Counter(item["expected_status"] for item in accepted)),
            "domains": dict(Counter(domain for item in accepted for domain in item["domains"])),
            "abstention_examples": sum(
                item["expected_status"] == "insufficient_evidence" for item in accepted
            ),
            "training_file": path.name,
        }
        (output_path / "devcontrol-stats.json").write_text(
            json.dumps(stats, ensure_ascii=True, indent=2) + "\n", encoding="utf-8"
        )
        return stats


def evaluate_devcontrol(
    model: NexusMicroModel,
    tokenizer: NexusTokenizer,
    benchmark_path: str | Path,
    generation_length: int = 32,
) -> dict:
    cases = json.loads(Path(benchmark_path).read_text(encoding="utf-8"))
    results = []
    for case in cases:
        prompt_ids = tokenizer.encode(case["prompt"])
        generated = tokenizer.decode(
            model.generate(prompt_ids, generation_length)[len(prompt_ids):],
            skip_special_tokens=True,
        )
        expected = case["expected_status"]
        abstained = DevControlSpecialization._is_abstention(generated)
        if expected == "insufficient_evidence":
            status_score = 1.0 if abstained else 0.0
        else:
            status_score = 0.0 if abstained else 1.0
        evidence_matches = sum(
            term.casefold() in generated.casefold()
            for term in case.get("expected_contains", [])
        )
        expected_terms = len(case.get("expected_contains", []))
        results.append({
            "id": case["id"],
            "task": case["task"],
            "expected_status": expected,
            "abstained": abstained,
            "status_score": status_score,
            "evidence_score": evidence_matches / expected_terms if expected_terms else 0.0,
        })
    return {
        "cases": len(results),
        "status_accuracy": sum(item["status_score"] for item in results) / len(results) if results else 0.0,
        "evidence_score": sum(item["evidence_score"] for item in results) / len(results) if results else 0.0,
        "results": results,
    }
