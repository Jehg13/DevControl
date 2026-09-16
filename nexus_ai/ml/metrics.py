"""Metrics implemented locally to avoid external ML dependencies."""

from typing import Any


def classification_metrics(actual: list[str], predicted: list[str]) -> dict[str, Any]:
    labels = sorted(set(actual) | set(predicted))
    matrix = {label: {column: 0 for column in labels} for label in labels}
    for expected, received in zip(actual, predicted):
        matrix[expected][received] += 1

    accuracy = sum(expected == received for expected, received in zip(actual, predicted)) / len(actual) if actual else 0.0
    per_label: dict[str, dict[str, float]] = {}
    for label in labels:
        true_positive = matrix[label][label]
        false_positive = sum(matrix[row][label] for row in labels if row != label)
        false_negative = sum(matrix[label][column] for column in labels if column != label)
        precision = true_positive / (true_positive + false_positive) if true_positive + false_positive else 0.0
        recall = true_positive / (true_positive + false_negative) if true_positive + false_negative else 0.0
        f1 = 2 * precision * recall / (precision + recall) if precision + recall else 0.0
        per_label[label] = {"precision": precision, "recall": recall, "f1": f1}

    count = len(per_label)
    return {
        "samples": len(actual),
        "accuracy": accuracy,
        "precision_macro": sum(item["precision"] for item in per_label.values()) / count if count else 0.0,
        "recall_macro": sum(item["recall"] for item in per_label.values()) / count if count else 0.0,
        "f1_macro": sum(item["f1"] for item in per_label.values()) / count if count else 0.0,
        "per_label": per_label,
        "confusion_matrix": matrix,
    }
