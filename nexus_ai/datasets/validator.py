"""Validation for versioned JSONL datasets."""

import json
from pathlib import Path
from typing import Any

from .schema import validate_record_shape


class DatasetValidationError(ValueError):
    def __init__(self, errors: list[str]):
        self.errors = errors
        super().__init__("\n".join(errors))


def validate_dataset_directory(directory: str | Path) -> dict[str, Any]:
    root = Path(directory)
    errors: list[str] = []
    records: list[dict[str, Any]] = []
    ids: set[str] = set()
    inputs: set[str] = set()

    for path in sorted(root.glob("*.jsonl")):
        with path.open(encoding="utf-8") as handle:
            for line_number, line in enumerate(handle, start=1):
                if not line.strip():
                    continue
                location = f"{path.name}:{line_number}"
                try:
                    record = json.loads(line)
                except json.JSONDecodeError as exc:
                    errors.append(f"{location}: invalid JSON ({exc.msg})")
                    continue
                if not isinstance(record, dict):
                    errors.append(f"{location}: record must be an object")
                    continue
                shape_errors = validate_record_shape(record)
                errors.extend(f"{location}: {error}" for error in shape_errors)
                record_id = record.get("id")
                if isinstance(record_id, str):
                    if record_id in ids:
                        errors.append(f"{location}: duplicate id: {record_id}")
                    ids.add(record_id)
                text = record.get("input")
                if isinstance(text, str):
                    normalized = " ".join(text.casefold().split())
                    if normalized in inputs:
                        errors.append(f"{location}: duplicate input")
                    inputs.add(normalized)
                records.append(record)

    if errors:
        raise DatasetValidationError(errors)
    return {"records": len(records), "ids": len(ids), "files": len(list(root.glob("*.jsonl")))}
