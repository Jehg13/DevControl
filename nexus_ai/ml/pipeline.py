"""Training, evaluation, persistence, and inference for Nexus classifiers."""

import json
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Any

from nexus_ai.nlp.normalizer import TextNormalizer
from nexus_ai.nlp.tokenizer import Tokenizer

from .classifier import MultinomialNaiveBayes
from .dataset import load_jsonl
from .metrics import classification_metrics

MODEL_VERSION = "1.0.0"
TARGETS = {
    "intent": lambda target: target["intent"],
    "action": lambda target: target["action"],
    "entity": lambda target: target["entity"],
    "needs_clarification": lambda target: str(target["needs_clarification"]).lower(),
}


@dataclass(frozen=True)
class TrainingReport:
    model_version: str
    train_samples: int
    validation_samples: int
    test_samples: int | None
    metrics: dict[str, dict[str, Any]]
    test_status: str

    def to_dict(self) -> dict[str, Any]:
        return asdict(self)


class NexusMlPipeline:
    def __init__(self, normalizer: TextNormalizer | None = None, tokenizer: Tokenizer | None = None):
        self.normalizer = normalizer or TextNormalizer()
        self.tokenizer = tokenizer or Tokenizer()
        self.models: dict[str, MultinomialNaiveBayes] = {}

    def _features(self, text: str) -> list[str]:
        normalized = self.normalizer.normalize(text)
        tokens = self.tokenizer.tokenize(normalized)
        return tokens + [f"{tokens[index]}::{tokens[index + 1]}" for index in range(len(tokens) - 1)]

    def train(
        self,
        train_path: str | Path,
        validation_path: str | Path,
        test_path: str | Path | None = None,
        model_path: str | Path | None = None,
    ) -> TrainingReport:
        train_records = load_jsonl(train_path, expected_split="train")
        validation_records = load_jsonl(validation_path, expected_split="validation")
        test_records = load_jsonl(test_path, expected_split="test") if test_path else []
        train_features = [self._features(record["input"]) for record in train_records]
        validation_features = [self._features(record["input"]) for record in validation_records]
        self.models = {}
        metrics: dict[str, dict[str, Any]] = {}
        for name, target_value in TARGETS.items():
            model = MultinomialNaiveBayes().fit(
                train_features,
                [target_value(record["target"]) for record in train_records],
            )
            self.models[name] = model
            expected = [target_value(record["target"]) for record in validation_records]
            metrics[name] = classification_metrics(expected, model.predict(validation_features))
        report = TrainingReport(
            model_version=MODEL_VERSION,
            train_samples=len(train_records),
            validation_samples=len(validation_records),
            test_samples=len(test_records) or None,
            metrics=metrics,
            test_status="not_available: no test dataset was provided",
        )
        if model_path:
            self.save(model_path, report)
        return report

    def predict(self, text: str) -> dict[str, Any]:
        if not self.models:
            raise ValueError("model has not been trained or loaded")
        features = self._features(text)
        prediction = {}
        confidences = {}
        for name, model in self.models.items():
            prediction[name], confidences[name] = model.predict_one_with_confidence(features)
        prediction["confidence"] = confidences
        prediction["executable"] = False
        return prediction

    def save(self, path: str | Path, report: TrainingReport | None = None) -> None:
        payload = {
            "model_version": MODEL_VERSION,
            "report": report.to_dict() if report else None,
            "models": {name: model.to_dict() for name, model in self.models.items()},
        }
        destination = Path(path)
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")

    def load(self, path: str | Path) -> None:
        payload = json.loads(Path(path).read_text(encoding="utf-8"))
        if payload.get("model_version") != MODEL_VERSION:
            raise ValueError("unsupported model version")
        self.models = {
            name: MultinomialNaiveBayes.from_dict(data)
            for name, data in payload["models"].items()
        }
