"""Dataset loading for the Nexus ML pipeline."""

import json
from pathlib import Path
from typing import Any


def load_jsonl(path: str | Path, expected_split: str | None = None) -> list[dict[str, Any]]:
    records: list[dict[str, Any]] = []
    with Path(path).open(encoding="utf-8") as handle:
        for line_number, line in enumerate(handle, start=1):
            if not line.strip():
                continue
            record = json.loads(line)
            if expected_split is not None and record.get("split") != expected_split:
                raise ValueError(f"{path}:{line_number} has split {record.get('split')!r}")
            records.append(record)
    if not records:
        raise ValueError(f"Dataset vacío: {path}")
    return records
