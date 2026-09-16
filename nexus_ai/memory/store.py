"""In-memory and JSON-persistable storage for conversation memories."""

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from .models import MemoryRecord


class ConversationMemoryStore:
    """Stores conversational references only; it is not a DevControl database."""

    def __init__(self, ttl_seconds: int = 1800):
        if ttl_seconds <= 0:
            raise ValueError("ttl_seconds must be positive")
        self.ttl_seconds = ttl_seconds
        self._records: dict[str, list[MemoryRecord]] = {}

    def add(self, session_id: str, kind: str, content: dict[str, Any], *, persistent: bool = False) -> MemoryRecord:
        now = datetime.now(timezone.utc)
        record = MemoryRecord(
            record_id=f"{session_id}:{len(self._records.get(session_id, [])) + 1}",
            session_id=session_id,
            kind=kind,
            content=content,
            created_at=now,
            expires_at=None if persistent else now.replace(microsecond=now.microsecond) + self._ttl(),
            source="persistent" if persistent else "conversation",
        )
        self._records.setdefault(session_id, []).append(record)
        return record

    def recent(self, session_id: str, limit: int = 10, now: datetime | None = None) -> list[MemoryRecord]:
        current = now or datetime.now(timezone.utc)
        records = [record for record in self._records.get(session_id, []) if not record.is_expired(current)]
        self._records[session_id] = records
        return list(reversed(records[-limit:]))

    def search(self, session_id: str, terms: set[str], limit: int = 10) -> list[MemoryRecord]:
        records = self.recent(session_id, limit=100)
        if not terms:
            return records[:limit]
        scored = [
            (sum(term in json.dumps(record.content, ensure_ascii=False).casefold() for term in terms), record)
            for record in records
        ]
        return [record for score, record in sorted(scored, key=lambda item: (item[0], item[1].created_at), reverse=True) if score > 0][:limit]

    def export_json(self, path: str | Path) -> None:
        payload = [record.to_dict() for records in self._records.values() for record in records]
        destination = Path(path)
        destination.parent.mkdir(parents=True, exist_ok=True)
        destination.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")

    def _ttl(self):
        from datetime import timedelta
        return timedelta(seconds=self.ttl_seconds)
