from __future__ import annotations

import hashlib
import json
from collections import Counter
from dataclasses import dataclass
from pathlib import Path
from typing import Iterable

from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


STAGES = (
    "objective",
    "available_information",
    "missing_information",
    "hypotheses",
    "hypothesis_evaluation",
    "strategy",
    "actions",
    "expected_results",
    "validation",
)
HYPOTHESIS_STATUSES = {"supported", "plausible", "rejected", "unknown"}
UNCERTAINTY_LEVELS = {"low", "medium", "high", "unknown"}


@dataclass(frozen=True)
class ReasoningExample:
    example_id: str
    objective: str
    available_information: tuple[str, ...]
    missing_information: tuple[str, ...]
    hypotheses: tuple[dict[str, object], ...]
    selected_strategy: str
    actions: tuple[str, ...]
    expected_results: tuple[str, ...]
    validation: tuple[str, ...]
    uncertainty: str
    license: str = "proprietary-authorized"
    permitted: bool = True
    metadata: dict[str, str] | None = None


class TechnicalReasoning:
    def __init__(self, tokenizer: NexusTokenizer) -> None:
        self.tokenizer = tokenizer

    @staticmethod
    def read_jsonl(path: str | Path) -> list[ReasoningExample]:
        examples: list[ReasoningExample] = []
        for line_number, line in enumerate(Path(path).read_text(encoding="utf-8").splitlines(), 1):
            if not line.strip():
                continue
            try:
                value = json.loads(line)
                for key in (
                    "available_information", "missing_information", "hypotheses",
                    "actions", "expected_results", "validation",
                ):
                    value[key] = tuple(value.get(key, ()))
                examples.append(ReasoningExample(**value))
            except (TypeError, ValueError, KeyError) as error:
                raise ValueError(f"invalid reasoning example line {line_number}: {error}") from error
        return examples

    @staticmethod
    def _valid_hypothesis(hypothesis: dict[str, object]) -> bool:
        required = {"statement", "status", "confidence", "evidence"}
        if not required.issubset(hypothesis):
            return False
        return (
            hypothesis["status"] in HYPOTHESIS_STATUSES
            and isinstance(hypothesis["confidence"], (int, float))
            and 0 <= float(hypothesis["confidence"]) <= 1
            and isinstance(hypothesis["evidence"], list)
        )

    def build(self, examples: Iterable[ReasoningExample], output: str | Path) -> dict:
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        accepted: list[dict] = []
        rejected = Counter()
        seen: set[str] = set()
        for example in examples:
            if not example.permitted or not example.license:
                rejected["unauthorized"] += 1
                continue
            if not example.objective.strip() or not example.selected_strategy.strip():
                rejected["empty_required_field"] += 1
                continue
            if example.uncertainty not in UNCERTAINTY_LEVELS:
                rejected["invalid_uncertainty"] += 1
                continue
            if not example.hypotheses or not all(self._valid_hypothesis(item) for item in example.hypotheses):
                rejected["invalid_hypothesis"] += 1
                continue
            if not example.available_information and not example.missing_information:
                rejected["missing_information_boundary"] += 1
                continue
            payload = {
                "objective": example.objective,
                "available_information": list(example.available_information),
                "missing_information": list(example.missing_information),
                "hypotheses": list(example.hypotheses),
                "selected_strategy": example.selected_strategy,
                "actions": list(example.actions),
                "expected_results": list(example.expected_results),
                "validation": list(example.validation),
                "uncertainty": example.uncertainty,
            }
            digest = hashlib.sha256(
                json.dumps(payload, ensure_ascii=True, sort_keys=True).encode()
            ).hexdigest()
            if digest in seen:
                rejected["duplicate"] += 1
                continue
            seen.add(digest)
            text = (
                "<|user|>technical reasoning\n"
                f"OBJECTIVE: {example.objective}\n"
                f"AVAILABLE: {'; '.join(example.available_information)}\n"
                f"MISSING: {'; '.join(example.missing_information)}\n"
                f"HYPOTHESES: {json.dumps(list(example.hypotheses), ensure_ascii=True)}\n"
                f"STRATEGY: {example.selected_strategy}\n"
                f"ACTIONS: {'; '.join(example.actions)}\n"
                f"EXPECTED: {'; '.join(example.expected_results)}\n"
                f"VALIDATION: {'; '.join(example.validation)}\n"
                f"UNCERTAINTY: {example.uncertainty}"
            )
            accepted.append({
                "id": example.example_id,
                **payload,
                "example_sha256": digest,
                "license": example.license,
                "input_ids": self.tokenizer.encode(text, add_bos=True, add_eos=True),
                "metadata": example.metadata or {},
            })
        path = output_path / "training-technical-reasoning.jsonl"
        with path.open("w", encoding="utf-8", newline="\n") as handle:
            for item in accepted:
                handle.write(json.dumps(item, ensure_ascii=True, sort_keys=True) + "\n")
        stats = {
            "format": "nexus-technical-reasoning-v1",
            "accepted": len(accepted),
            "rejected": dict(rejected),
            "uncertainty_levels": dict(Counter(item["uncertainty"] for item in accepted)),
            "hypothesis_statuses": dict(
                Counter(h["status"] for item in accepted for h in item["hypotheses"])
            ),
            "training_file": path.name,
        }
        (output_path / "reasoning-stats.json").write_text(
            json.dumps(stats, ensure_ascii=True, indent=2) + "\n", encoding="utf-8"
        )
        return stats


def evaluate_reasoning(
    model: NexusMicroModel,
    tokenizer: NexusTokenizer,
    benchmark_path: str | Path,
    generation_length: int = 48,
) -> dict:
    cases = json.loads(Path(benchmark_path).read_text(encoding="utf-8"))
    results = []
    for case in cases:
        prompt_ids = tokenizer.encode(case["prompt"])
        output = tokenizer.decode(
            model.generate(prompt_ids, generation_length)[len(prompt_ids):],
            skip_special_tokens=True,
        ).casefold()
        stage_scores = {
            stage: int(any(term.casefold() in output for term in case.get("expected", {}).get(stage, [])))
            for stage in STAGES
        }
        uncertainty_terms = [term.casefold() for term in case.get("uncertainty_terms", [])]
        uncertainty_score = int(any(term in output for term in uncertainty_terms))
        forbidden = [term.casefold() for term in case.get("forbidden_as_fact", [])]
        fact_violation = int(any(term in output for term in forbidden))
        results.append({
            "id": case["id"],
            "stage_scores": stage_scores,
            "stage_coverage": sum(stage_scores.values()) / len(STAGES),
            "uncertainty_score": uncertainty_score,
            "fact_violation": fact_violation,
        })
    return {
        "cases": len(results),
        "stage_scores": {
            stage: sum(item["stage_scores"][stage] for item in results) / len(results)
            if results else 0.0
            for stage in STAGES
        },
        "uncertainty_rate": sum(item["uncertainty_score"] for item in results) / len(results)
        if results else 0.0,
        "fact_violation_rate": sum(item["fact_violation"] for item in results) / len(results)
        if results else 0.0,
        "results": results,
    }
