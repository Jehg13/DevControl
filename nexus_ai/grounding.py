"""Validation that Nexus answers are supported by Laravel evidence."""

from dataclasses import dataclass, field
from typing import Any


@dataclass(frozen=True)
class GroundingResult:
    grounded: bool
    evidence: list[dict[str, Any]] = field(default_factory=list)
    missing: list[str] = field(default_factory=list)
    absence: bool = False
    ambiguous: bool = False

    def to_dict(self) -> dict[str, Any]:
        return {
            "grounded": self.grounded,
            "evidence": self.evidence,
            "missing": self.missing,
            "absence": self.absence,
            "ambiguous": self.ambiguous,
        }


class GroundingValidator:
    """Accepts only facts returned by authorized Laravel tools as data evidence."""

    def validate(
        self,
        interpretation: dict[str, Any],
        reasoning: dict[str, Any],
        tool_results: list[dict[str, Any]],
        recovered_data: dict[str, Any] | None = None,
    ) -> GroundingResult:
        evidence: list[dict[str, Any]] = []
        successful_results = [
            result for result in tool_results
            if (
                isinstance(result, dict)
                and result.get("ok") is True
                and "data" in result
                and result.get("data") is not None
            )
        ]

        for result in successful_results:
            data = result.get("data")
            records = data if isinstance(data, list) else [data]
            evidence.append({
                "source": "laravel_tool",
                "tool": result.get("meta", {}).get("tool") if isinstance(result.get("meta"), dict) else None,
                "entity": result.get("meta", {}).get("entity") if isinstance(result.get("meta"), dict) else None,
                "record_ids": [
                    record.get("id") for record in records
                    if isinstance(record, dict) and record.get("id") is not None
                ],
                "record_count": len(records) if data is not None else 0,
            })

        if recovered_data:
            for entity, records in recovered_data.items():
                if isinstance(records, list):
                    evidence.append({
                        "source": "laravel_recovered_data",
                        "tool": None,
                        "entity": entity,
                        "record_ids": [
                            record.get("id") for record in records
                            if isinstance(record, dict) and record.get("id") is not None
                        ],
                        "record_count": len(records),
                    })

        is_query = interpretation.get("action") == "query" or str(
            interpretation.get("intent", "")
        ).startswith("query_")
        if not is_query:
            return GroundingResult(
                grounded=bool(evidence or reasoning.get("findings")),
                evidence=evidence,
            )

        if interpretation.get("requires_clarification"):
            return GroundingResult(
                grounded=False,
                evidence=evidence,
                missing=["La consulta necesita aclaración antes de recuperar datos."],
                ambiguous=True,
            )
        if not successful_results and not recovered_data:
            return GroundingResult(
                grounded=False,
                missing=["No se recibió evidencia verificable de Laravel para esta consulta."],
            )

        return GroundingResult(
            grounded=True,
            evidence=evidence,
            absence=bool(successful_results)
            and all(result.get("data") == [] for result in successful_results)
            and not recovered_data,
        )

    @staticmethod
    def _reasoning_facts(reasoning: dict[str, Any]) -> bool:
        return any(
            isinstance(finding, dict)
            and finding.get("finding_type") == "fact"
            and finding.get("sources")
            for finding in reasoning.get("findings", [])
        )
