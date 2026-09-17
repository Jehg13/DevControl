"""In-memory and JSON-persistable storage for conversation memories."""

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

from .models import MemoryRecord


class ConversationMemoryStore:
    """Stores conversational references only; it is not a DevControl database."""

    def __init__(self, ttl_seconds: int = 1800, path: str | Path | None = None, max_records: int = 50):
        if ttl_seconds <= 0:
            raise ValueError("ttl_seconds must be positive")
        if max_records <= 0:
            raise ValueError("max_records must be positive")
        self.ttl_seconds = ttl_seconds
        self.max_records = max_records
        self.path = Path(path) if path else None
        self._records: dict[str, list[MemoryRecord]] = {}
        self._load()

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
        self._records[session_id] = self._records[session_id][-self.max_records:]
        self._save()
        return record

    def recent(self, session_id: str, limit: int = 10, now: datetime | None = None) -> list[MemoryRecord]:
        current = now or datetime.now(timezone.utc)
        records = [record for record in self._records.get(session_id, []) if not record.is_expired(current)]
        self._records[session_id] = records
        self._save()
        return list(reversed(records[-limit:]))

    def clear(self, session_id: str) -> None:
        self._records.pop(session_id, None)
        self._save()

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

    def _load(self) -> None:
        if self.path is None or not self.path.exists():
            return
        try:
            payload = json.loads(self.path.read_text(encoding="utf-8"))
            for item in payload:
                created = datetime.fromisoformat(item["created_at"])
                expires = datetime.fromisoformat(item["expires_at"]) if item.get("expires_at") else None
                record = MemoryRecord(
                    record_id=item["record_id"],
                    session_id=item["session_id"],
                    kind=item["kind"],
                    content=item["content"],
                    created_at=created,
                    expires_at=expires,
                    source=item.get("source", "conversation"),
                )
                self._records.setdefault(record.session_id, []).append(record)
        except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError):
            self._records = {}

    def _save(self) -> None:
        if self.path is None:
            return
        self.path.parent.mkdir(parents=True, exist_ok=True)
        payload = [record.to_dict() for records in self._records.values() for record in records]
        self.path.write_text(json.dumps(payload, ensure_ascii=False, indent=2), encoding="utf-8")
