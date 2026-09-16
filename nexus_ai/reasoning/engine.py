"""Deterministic reasoning over NLP output and supplied DevControl evidence."""

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.nlp import NexusNlpInterpreter

from .models import ReasoningFinding, ReasoningRequest, ReasoningResult


class NexusReasoner:
    """Produces conclusions only from supplied evidence; never executes actions."""

    def __init__(self, interpreter: NexusNlpInterpreter | None = None, ml_pipeline=None):
        self.interpreter = interpreter or NexusNlpInterpreter()
        self.ml_pipeline = ml_pipeline

    def analyze(self, request: ReasoningRequest, graph: KnowledgeGraph | None = None) -> ReasoningResult:
        interpretation = self.interpreter.interpret(request.message)
        interpretation_data = interpretation.to_dict()
        if self.ml_pipeline is not None:
            interpretation_data["ml_prediction"] = self.ml_pipeline.predict(request.message)
        findings = self._facts(request.recovered_data)
        findings.extend(self._contradictions(request.context))
        if graph is not None:
            findings.extend(self._graph_facts(graph, interpretation.entity))
        findings.extend(self._inferences(interpretation_data, request.recovered_data))
        needed = self._information_needed(interpretation_data, request.recovered_data, graph)
        findings.extend(ReasoningFinding("missing", item) for item in needed)
        status = "insufficient_data" if needed else (
            "contradictory" if any(f.finding_type == "contradiction" for f in findings) else "analyzed"
        )
        steps = [
            "Interpreté el mensaje con el NLP local.",
            "Usé únicamente datos recuperados y relaciones del grafo suministrado.",
            "Separé hechos confirmados, inferencias, contradicciones y datos faltantes.",
        ]
        return ReasoningResult(interpretation_data, needed, findings, steps, status, executable=False)

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
