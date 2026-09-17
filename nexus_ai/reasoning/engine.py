"""Deterministic reasoning over NLP output and supplied DevControl evidence."""

import hashlib
import json

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.nlp import NexusNlpInterpreter

from .models import ReasoningFinding, ReasoningRequest, ReasoningResult


class NexusReasoner:
    """Produces conclusions only from supplied evidence; never executes actions."""

    def __init__(self, interpreter: NexusNlpInterpreter | None = None, ml_pipeline=None):
        self.interpreter = interpreter or NexusNlpInterpreter()
        self.ml_pipeline = ml_pipeline

    def analyze(self, request: ReasoningRequest, graph: KnowledgeGraph | None = None) -> ReasoningResult:
        interpretation = self.interpreter.interpret(request.message, request.context)
        interpretation_data = interpretation.to_dict()
        if self.ml_pipeline is not None:
            interpretation_data["ml_prediction"] = self.ml_pipeline.predict(request.message)
        findings = self._facts(request.recovered_data)
        findings.extend(self._observations(request.recovered_data))
        findings.extend(self._contradictions(request.context))
        if graph is not None:
            findings.extend(self._graph_facts(graph, interpretation.entity))
        hypotheses = self._hypotheses(request.recovered_data)
        findings.extend(hypotheses)
        findings.extend(self._inferences(interpretation_data, request.recovered_data))
        needed = self._information_needed(interpretation_data, request.recovered_data, graph)
        findings.extend(ReasoningFinding("missing", item) for item in needed)
        contradictions = [f for f in findings if f.finding_type == "contradiction"]
        if contradictions:
            findings = [
                ReasoningFinding(
                    finding.finding_type,
                    finding.statement,
                    finding.evidence,
                    finding.confidence,
                    "discarded" if finding.finding_type == "hypothesis" else finding.status,
                )
                for finding in findings
            ]
        conclusion = self._conclusion(findings, needed, contradictions)
        status = "insufficient_data" if needed else (
            "contradictory" if contradictions else "analyzed"
        )
        steps = [
            "Interpreté el mensaje con el NLP local.",
            "Usé únicamente datos recuperados y relaciones del grafo suministrado.",
            "Separé hechos confirmados, inferencias, contradicciones y datos faltantes.",
        ]
        fingerprint = hashlib.sha256(
            json.dumps(
                self._evidence_payload(request, graph),
                ensure_ascii=False,
                sort_keys=True,
                default=str,
            ).encode("utf-8")
        ).hexdigest()
        return ReasoningResult(
            interpretation_data,
            needed,
            findings,
            steps,
            status,
            executable=False,
            conclusion=conclusion,
            evidence_fingerprint=fingerprint,
        )

    def _information_needed(self, interpretation, data, graph):
        if interpretation["requires_clarification"]:
            return [interpretation["clarification_question"] or "Se necesita aclarar la solicitud."]
        if interpretation["action"] in {"query", "analyze"} and not data and graph is None:
            return ["Se necesitan datos actuales de DevControl para responder."]
        return []

    def _facts(self, data):
        findings = []
        for entity, records in data.items():
            if not isinstance(records, list):
                continue
            for record in records:
                if isinstance(record, dict):
                    identifier = record.get("id", "sin identificador")
                    findings.append(ReasoningFinding(
                        "fact",
                        f"El registro {entity} {identifier} está confirmado por los datos recuperados.",
                        [{"source": "recovered_data", "entity": entity, "record": record}],
                    ))
        return findings

    def _observations(self, data):
        observations = []
        for entity, records in data.items():
            if not isinstance(records, list) or not records:
                continue
            observations.append(ReasoningFinding(
                "observation",
                f"Se observaron {len(records)} registros de {entity}.",
                [{"source": "recovered_data", "entity": entity, "count": len(records)}],
                1.0,
            ))
        return observations

    def _hypotheses(self, data):
        records = [
            record for values in data.values() if isinstance(values, list)
            for record in values if isinstance(record, dict)
        ]
        hypotheses = []
        high_priority = [
            record for record in records
            if str(record.get("priority") or record.get("prioridad", "")).casefold()
            in {"alta", "critica", "crítica"}
        ]
        open_items = [
            record for record in records
            if str(record.get("status") or record.get("estado", "")).casefold()
            in {"abierto", "pendiente", "reportado"}
        ]
        if high_priority:
            hypotheses.append(ReasoningFinding(
                "hypothesis",
                "Los registros de alta prioridad podrían requerir atención prioritaria.",
                [{"source": "recovered_data", "count": len(high_priority)}],
                0.7,
            ))
        if open_items:
            hypotheses.append(ReasoningFinding(
                "hypothesis",
                "Los registros abiertos o pendientes podrían representar trabajo no resuelto.",
                [{"source": "recovered_data", "count": len(open_items)}],
                0.7,
            ))
        return hypotheses

    def _graph_facts(self, graph, entity):
        assertions = graph.query(assertion_type="fact")
        if entity:
            node_ids = {node.node_id for node in graph.nodes(entity)}
            assertions = [item for item in assertions if item.subject in node_ids]
        return [ReasoningFinding("fact", f"{item.subject} {item.predicate} {item.object}.", [item.to_dict()])
                for item in assertions]

    def _inferences(self, interpretation, data):
        if interpretation["action"] not in {"query", "analyze"}:
            return []
        if interpretation.get("operation") == "compare" and interpretation.get("entity") == "proyecto":
            projects = {item.get("id"): item for item in data.get("proyecto", []) if isinstance(item, dict)}
            bugs = {}
            for bug in data.get("bug", []):
                if isinstance(bug, dict):
                    bugs[bug.get("proyecto_id")] = bugs.get(bug.get("proyecto_id"), 0) + 1
            if bugs:
                project_id, count = max(bugs.items(), key=lambda item: item[1])
                project = projects.get(project_id, {})
                name = project.get("nombre", f"proyecto {project_id}")
                return [ReasoningFinding(
                    "inference",
                    f"El proyecto {name} tiene más bugs en los datos consultados ({count}).",
                    [{"source": "recovered_data", "project_id": project_id, "bugs_count": count}],
                    1.0,
                )]
        records = [record for values in data.values() if isinstance(values, list)
                   for record in values if isinstance(record, dict)]
        high_risk = [record for record in records if str(record.get("priority", "")).casefold() in {"alta", "critica"}]
        open_items = [record for record in records if str(record.get("status", "")).casefold() in {"abierto", "pendiente", "reportado"}]
        if high_risk and open_items:
            return [ReasoningFinding(
                "inference",
                "La combinación de prioridad alta/crítica y estado abierto/pendiente sugiere atención prioritaria.",
                [{"source": "recovered_data", "high_priority_count": len(high_risk), "open_count": len(open_items)}],
                0.8,
            )]
        return []

    def _contradictions(self, context):
        return [ReasoningFinding(
            "contradiction",
            f"El contexto contiene datos contradictorios para {item.get('key', 'un elemento')}.",
            item.get("records", []),
        ) for item in context.get("contradictions", [])]

    def _conclusion(self, findings, needed, contradictions):
        evidence = [
            evidence for finding in findings
            if finding.finding_type in {"fact", "observation", "inference"}
            for evidence in finding.evidence
        ]
        if needed:
            return ReasoningFinding(
                "conclusion",
                "No hay información suficiente para establecer una conclusión confirmada.",
                evidence or [{"source": "reasoning", "type": "missing_information"}],
                0.0,
            )
        if contradictions:
            return ReasoningFinding(
                "conclusion",
                "No se puede establecer una conclusión única porque existen contradicciones.",
                evidence or [{"source": "reasoning", "type": "contradiction"}],
                0.0,
            )
        inferences = [f for f in findings if f.finding_type == "inference"]
        if inferences:
            return ReasoningFinding(
                "conclusion",
                "La evidencia permite una conclusión inferida, no un hecho confirmado.",
                evidence,
                min((f.confidence or 0.0) for f in inferences),
            )
        if not evidence:
            return ReasoningFinding(
                "conclusion",
                "No se recibió evidencia verificable para establecer una conclusión.",
                [{"source": "reasoning", "type": "no_evidence"}],
                0.0,
            )
        return ReasoningFinding(
            "conclusion",
            "La evidencia disponible confirma los hechos observados.",
            evidence,
            1.0,
        )

    def _evidence_payload(self, request, graph):
        return {
            "recovered_data": request.recovered_data,
            "context": request.context,
            "knowledge": graph.export() if graph else None,
        }
