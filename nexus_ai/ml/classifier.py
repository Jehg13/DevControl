"""A small local multinomial Naive Bayes classifier for Nexus labels."""

import math
from collections import Counter, defaultdict
from typing import Any


class MultinomialNaiveBayes:
    def __init__(self) -> None:
        self.labels: list[str] = []
        self.vocabulary: list[str] = []
        self.label_document_counts: dict[str, int] = {}
        self.label_token_counts: dict[str, dict[str, int]] = {}
        self.label_total_tokens: dict[str, int] = {}
        self.document_count = 0

    def fit(self, documents: list[list[str]], labels: list[str]) -> "MultinomialNaiveBayes":
        if not documents or len(documents) != len(labels):
            raise ValueError("documents and labels must have the same non-zero length")
        vocabulary = set(token for document in documents for token in document)
        self.vocabulary = sorted(vocabulary)
        self.labels = sorted(set(labels))
        self.document_count = len(documents)
        self.label_document_counts = Counter(labels)
        self.label_token_counts = {label: defaultdict(int) for label in self.labels}
        self.label_total_tokens = {label: 0 for label in self.labels}
        for document, label in zip(documents, labels):
            for token, count in Counter(document).items():
                self.label_token_counts[label][token] += count
                self.label_total_tokens[label] += count
        return self

    def predict_one(self, document: list[str]) -> str:
        return self.predict_one_with_confidence(document)[0]

    def predict_one_with_confidence(self, document: list[str]) -> tuple[str, float]:
        if not self.labels:
            raise ValueError("model has not been trained")
        vocabulary_size = max(len(self.vocabulary), 1)
        scores: dict[str, float] = {}
        for label in self.labels:
            score = math.log(self.label_document_counts[label] / self.document_count)
            denominator = self.label_total_tokens[label] + vocabulary_size
            for token, count in Counter(document).items():
                score += count * math.log((self.label_token_counts[label].get(token, 0) + 1) / denominator)
            scores[label] = score
        label = max(scores, key=scores.get)
        values = [math.exp(score) for score in scores.values()]
        total = sum(values)
        return label, (math.exp(scores[label]) / total if total else 0.0)

    def predict(self, documents: list[list[str]]) -> list[str]:
        return [self.predict_one(document) for document in documents]

    def to_dict(self) -> dict[str, Any]:
        return {
            "labels": self.labels,
            "vocabulary": self.vocabulary,
            "label_document_counts": self.label_document_counts,
            "label_token_counts": self.label_token_counts,
            "label_total_tokens": self.label_total_tokens,
            "document_count": self.document_count,
        }

    @classmethod
    def from_dict(cls, data: dict[str, Any]) -> "MultinomialNaiveBayes":
        model = cls()
        model.labels = data["labels"]
        model.vocabulary = data["vocabulary"]
        model.label_document_counts = data["label_document_counts"]
        model.label_token_counts = data["label_token_counts"]
        model.label_total_tokens = data["label_total_tokens"]
        model.document_count = data["document_count"]
        return model
