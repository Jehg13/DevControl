from __future__ import annotations

import hashlib
import json
from collections import Counter, defaultdict
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable

from nexus_tokenizer import NexusTokenizer
from nexus_training import NexusMicroModel


LANGUAGE_PRIORITY = (
    "PHP", "Laravel", "Dart", "Flutter", "SQL", "JavaScript", "TypeScript",
    "HTML/CSS", "Bash/PowerShell", "Git",
)
TASKS = {
    "complete_code", "explain_code", "find_error", "fix_error", "refactor",
    "generate_tests", "dependencies", "architecture", "migration", "api",
    "authentication", "database",
}


@dataclass(frozen=True)
class CodeExample:
    example_id: str
    language: str
    task: str
    prompt: str
    completion: str
    license: str
    permitted: bool = True
    metadata: dict[str, str] | None = None


class CodeSpecialization:
    def __init__(self, tokenizer: NexusTokenizer) -> None:
        self.tokenizer = tokenizer

    @staticmethod
    def read_jsonl(path: str | Path) -> list[CodeExample]:
        examples: list[CodeExample] = []
        for line_number, line in enumerate(Path(path).read_text(encoding="utf-8").splitlines(), 1):
            if not line.strip():
                continue
            try:
                examples.append(CodeExample(**json.loads(line)))
            except (TypeError, ValueError, KeyError) as error:
                raise ValueError(f"invalid code example line {line_number}: {error}") from error
        return examples

    def build(self, examples: Iterable[CodeExample], output: str | Path) -> dict:
        output_path = Path(output)
        output_path.mkdir(parents=True, exist_ok=True)
        accepted: list[dict] = []
        seen: set[str] = set()
        rejected = Counter()
        for example in examples:
            language = example.language.strip()
            if language not in LANGUAGE_PRIORITY:
                rejected["unsupported_language"] += 1
                continue
            if example.task not in TASKS:
                rejected["unsupported_task"] += 1
                continue
            if not example.permitted or not example.license:
                rejected["unauthorized"] += 1
                continue
            if not example.prompt.strip() or not example.completion.strip():
                rejected["empty"] += 1
                continue
            digest = hashlib.sha256(
                (language + "\n" + example.task + "\n" + example.prompt + "\n" + example.completion)
                .encode("utf-8")
            ).hexdigest()
            if digest in seen:
                rejected["duplicate"] += 1
                continue
            seen.add(digest)
            text = (
                f"<|user|>{language} {example.task}\n{example.prompt}"
                f"<|assistant|>{example.completion}"
            )
            ids = self.tokenizer.encode(text, add_bos=True, add_eos=True)
            accepted.append({
                "id": example.example_id,
                "language": language,
                "task": example.task,
                "license": example.license,
                "example_sha256": digest,
                "input_ids": ids,
                "metadata": example.metadata or {},
            })
        by_language: dict[str, list[dict]] = defaultdict(list)
        for item in accepted:
            by_language[item["language"]].append(item)
        files: list[str] = []
        for language in LANGUAGE_PRIORITY:
            items = by_language.get(language, [])
            if not items:
                continue
            filename = f"training-{language.lower().replace('/', '-').replace(' ', '-')}.jsonl"
            with (output_path / filename).open("w", encoding="utf-8", newline="\n") as handle:
                for item in items:
                    handle.write(json.dumps(item, sort_keys=True) + "\n")
            files.append(filename)
        stats = {
            "format": "nexus-code-specialization-v1",
            "languages": dict(Counter(item["language"] for item in accepted)),
            "tasks": dict(Counter(item["task"] for item in accepted)),
            "accepted": len(accepted),
            "rejected": dict(rejected),
            "files": files,
            "language_priority": list(LANGUAGE_PRIORITY),
        }
        (output_path / "specialization-stats.json").write_text(
            json.dumps(stats, indent=2) + "\n", encoding="utf-8"
        )
        return stats


def evaluate_benchmark(
    model: NexusMicroModel,
    tokenizer: NexusTokenizer,
    benchmark_path: str | Path,
    generation_length: int = 32,
) -> dict:
    cases = json.loads(Path(benchmark_path).read_text(encoding="utf-8"))
    results: list[dict] = []
    for case in cases:
        prompt_ids = tokenizer.encode(case["prompt"])
        generated_ids = model.generate(prompt_ids, generation_length)
        generated = tokenizer.decode(generated_ids[len(prompt_ids):], skip_special_tokens=True)
        expected = [str(value).lower() for value in case.get("expected_contains", [])]
        matched = sum(value in generated.lower() for value in expected)
        results.append({
            "id": case["id"],
            "language": case["language"],
            "task": case["task"],
            "matched": matched,
            "expected": len(expected),
            "score": matched / len(expected) if expected else 0,
            "output": generated,
        })
    by_language: dict[str, list[dict]] = defaultdict(list)
    for result in results:
        by_language[result["language"]].append(result)
    language_scores = {
        language: sum(item["score"] for item in items) / len(items)
        for language, items in by_language.items()
    }
    return {
        "cases": len(results),
        "overall_score": sum(item["score"] for item in results) / len(results) if results else 0,
        "language_scores": language_scores,
        "results": results,
    }
