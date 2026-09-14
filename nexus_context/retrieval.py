from __future__ import annotations

import hashlib
import json
import math
import re
from collections import Counter
from dataclasses import asdict, dataclass
from pathlib import Path
from typing import Iterable

from nexus_tokenizer import NexusTokenizer


MEMORY_TYPES = {
    "conversation",
    "long_term",
    "experience",
    "knowledge",
    "project_understanding",
    "internal_state",
}
TOKEN_RE = re.compile(r"[a-zA-Z0-9_./:-]+")


@dataclass(frozen=True)
class ContextItem:
    item_id: str
    memory_type: str
    text: str
    project_id: str | None = None
    session_id: str | None = None
    source: str | None = None
    timestamp: str | None = None
    metadata: dict[str, str] | None = None


@dataclass(frozen=True)
class RetrievalContext:
    query: str
    items: tuple[ContextItem, ...]
    token_ids: tuple[int, ...]
    scores: dict[str, float]
    omitted_items: int


def _terms(value: str) -> list[str]:
    return [term.casefold() for term in TOKEN_RE.findall(value)]


class ContextRetriever:
    """Deterministic lexical retrieval with scope and token-budget isolation."""

    def __init__(self, tokenizer: NexusTokenizer, items: Iterable[ContextItem] = ()) -> None:
        self.tokenizer = tokenizer
        self._items: dict[str, ContextItem] = {}
        for item in items:
            self.add(item)

    def add(self, item: ContextItem) -> None:
        if item.memory_type not in MEMORY_TYPES:
            raise ValueError(f"unsupported memory type: {item.memory_type}")
        if not item.text.strip():
            raise ValueError("context item text is required")
        self._items[item.item_id] = item

    def retrieve(
        self,
        query: str,
        *,
        project_id: str | None = None,
        session_id: str | None = None,
        memory_types: set[str] | None = None,
        max_items: int = 8,
        max_tokens: int = 1024,
    ) -> RetrievalContext:
        if max_items < 1 or max_tokens < 1:
            raise ValueError("max_items and max_tokens must be positive")
        query_terms = Counter(_terms(query))
        candidates = []
        for item in self._items.values():
            if project_id is not None and item.project_id != project_id:
                continue
            if session_id is not None and item.session_id != session_id:
                continue
            if memory_types is not None and item.memory_type not in memory_types:
                continue
            item_terms = Counter(_terms(item.text + " " + (item.source or "")))
            overlap = sum(min(count, item_terms[term]) for term, count in query_terms.items())
            if overlap == 0:
                continue
            norm = math.sqrt(sum(value * value for value in query_terms.values())) * math.sqrt(
                sum(value * value for value in item_terms.values())
            )
            score = overlap / norm if norm else 0.0
            candidates.append((score, item))
        candidates.sort(key=lambda value: (-value[0], value[1].item_id))
        selected: list[ContextItem] = []
        token_ids: list[int] = []
        scores: dict[str, float] = {}
        for score, item in candidates:
            if len(selected) >= max_items:
                break
            encoded = self.tokenizer.encode(item.text)
            if len(token_ids) + len(encoded) > max_tokens:
                continue
            selected.append(item)
            token_ids.extend(encoded)
            scores[item.item_id] = score
        return RetrievalContext(
            query=query,
            items=tuple(selected),
            token_ids=tuple(token_ids),
            scores=scores,
            omitted_items=len(candidates) - len(selected),
        )

    def build_model_context(self, context: RetrievalContext) -> dict:
        return {
            "query": context.query,
            "items": [
                {
                    "id": item.item_id,
                    "type": item.memory_type,
                    "text": item.text,
                    "source": item.source,
                    "score": context.scores[item.item_id],
                }
                for item in context.items
            ],
            "token_count": len(context.token_ids),
            "omitted_items": context.omitted_items,
        }


def evaluate_retrieval(
    retriever: ContextRetriever,
    benchmark_path: str | Path,
) -> dict:
    cases = json.loads(Path(benchmark_path).read_text(encoding="utf-8"))
    results = []
    for case in cases:
        context = retriever.retrieve(
            case["query"],
            project_id=case.get("project_id"),
            session_id=case.get("session_id"),
            memory_types=set(case["memory_types"]) if case.get("memory_types") else None,
            max_items=case.get("max_items", 8),
            max_tokens=case.get("max_tokens", 1024),
        )
        selected = {item.item_id for item in context.items}
        relevant = set(case.get("relevant_ids", []))
        forbidden = set(case.get("forbidden_ids", []))
        relevant_found = len(selected & relevant)
        results.append({
            "id": case["id"],
            "precision": relevant_found / len(selected) if selected else 0.0,
            "recall": relevant_found / len(relevant) if relevant else 1.0,
            "contamination": len(selected & forbidden) / len(selected) if selected else 0.0,
            "token_count": len(context.token_ids),
            "max_tokens": case.get("max_tokens", 1024),
            "selected_ids": sorted(selected),
        })
    return {
        "cases": len(results),
        "mean_precision": sum(item["precision"] for item in results) / len(results) if results else 0.0,
        "mean_recall": sum(item["recall"] for item in results) / len(results) if results else 0.0,
        "mean_contamination": sum(item["contamination"] for item in results) / len(results) if results else 0.0,
        "within_budget": all(item["token_count"] <= item["max_tokens"] for item in results),
        "results": results,
    }
