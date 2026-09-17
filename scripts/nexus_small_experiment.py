#!/usr/bin/env python3
from __future__ import annotations

import json
import math
import sys
import time
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_tokenizer import NexusTokenizer
from nexus_training.scalable import SMALL_ARCHITECTURE, ScalableCausalModel, save_checkpoint


def read_examples(path: Path) -> list[list[int]]:
    examples = []
    for file in sorted(path.glob("training-*.jsonl")):
        if file.name == "training-log.jsonl":
            continue
        for line in file.read_text(encoding="utf-8").splitlines():
            if line.strip():
                examples.append(json.loads(line)["input_ids"])
    return examples


def evaluate(model: ScalableCausalModel, examples: list[list[int]]) -> float:
    losses = []
    for sequence in examples:
        for source, target in zip(sequence, sequence[1:]):
            logits = model.logits(source)
            maximum = max(logits)
            total = sum(math.exp(value - maximum) for value in logits)
            losses.append(-(logits[target] - maximum - math.log(total)))
    return sum(losses) / len(losses)


def main() -> None:
    root = Path("storage/app/nexus-model")
    output = Path("storage/app/nexus-model-small")
    examples = read_examples(root)
    validation = []
    for file in sorted(root.glob("validation-*.jsonl")):
        validation.extend(json.loads(line)["input_ids"] for line in file.read_text(encoding="utf-8").splitlines() if line.strip())
    test = []
    for file in sorted(root.glob("test-*.jsonl")):
        test.extend(json.loads(line)["input_ids"] for line in file.read_text(encoding="utf-8").splitlines() if line.strip())
    started = time.perf_counter()
    model = ScalableCausalModel(SMALL_ARCHITECTURE, seed=1337)
    initial_loss = evaluate(model, validation)
    for sequence in examples:
        for source, target in zip(sequence, sequence[1:]):
            model.train_pair(source, target, 0.05)
    training_seconds = time.perf_counter() - started
    output.mkdir(parents=True, exist_ok=True)
    metadata = {
        "format": "nexus-small-experimental-v1",
        "architecture": SMALL_ARCHITECTURE.__dict__,
        "parameters": SMALL_ARCHITECTURE.parameters,
        "training_examples": len(examples),
        "initial_validation_loss": initial_loss,
        "validation_loss": evaluate(model, validation),
        "test_loss": evaluate(model, test),
        "training_seconds": training_seconds,
        "status": "experimental",
    }
    save_checkpoint(output / "experimental.json", model, metadata)
    (output / "metadata.json").write_text(json.dumps(metadata, indent=2) + "\n", encoding="utf-8")
    tokenizer = NexusTokenizer.load(root / "tokenizer.json")
    generated = tokenizer.decode(model.generate(tokenizer.encode("Hola Nexus"), 16), skip_special_tokens=True)
    print(json.dumps({**metadata, "generated": generated}, ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
