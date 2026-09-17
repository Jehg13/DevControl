"""Language-neutral representation of read-only code intelligence evidence."""

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True)
class CodeIntelligenceResult:
    project: dict[str, Any]
    files: tuple[dict[str, Any], ...] = ()
    directories: tuple[dict[str, Any], ...] = ()
    symbols: tuple[dict[str, Any], ...] = ()
    imports: tuple[dict[str, Any], ...] = ()
    relations: tuple[dict[str, Any], ...] = ()
    read_only: bool = True

    @classmethod
    def from_tool_result(cls, result: dict[str, Any]) -> "CodeIntelligenceResult":
        if not isinstance(result, dict) or result.get("ok") is not True:
            raise ValueError("code intelligence requires a successful tool result")
        data = result.get("data")
        if not isinstance(data, dict) or data.get("project", {}).get("root_type") != "directory":
            raise ValueError("code intelligence result must describe a directory project")
        if data["project"].get("read_only") is not True:
            raise ValueError("code intelligence evidence must be read-only")
        return cls(
            project=data["project"],
            files=tuple(cls._items(data, "files", "file")),
            directories=tuple(cls._items(data, "directories", "directory")),
            symbols=tuple(cls._symbols(data.get("symbols", []))),
            imports=tuple(cls._items(data, "imports")),
            relations=tuple(cls._items(data, "relations")),
            read_only=True,
        )

    @staticmethod
    def _items(data: dict[str, Any], key: str, item_type: str | None = None) -> list[dict[str, Any]]:
        values = data.get(key, [])
        if not isinstance(values, list):
            raise ValueError(f"{key} must be a list")
        items = [value for value in values if isinstance(value, dict)]
        if len(items) != len(values):
            raise ValueError(f"{key} contains invalid entries")
        if item_type and any(value.get("type") != item_type for value in items):
            raise ValueError(f"{key} contains an invalid type")
        return items

    @staticmethod
    def _symbols(values: Any) -> list[dict[str, Any]]:
        if not isinstance(values, list):
            raise ValueError("symbols must be a list")
        allowed = {"class", "interface", "method", "function"}
        for value in values:
            if not isinstance(value, dict) or value.get("kind") not in allowed:
                raise ValueError("symbols contain an invalid kind")
            if not isinstance(value.get("file"), str) or not isinstance(value.get("name"), str):
                raise ValueError("symbols require name and file")
        return values
