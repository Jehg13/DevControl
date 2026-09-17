"""Deterministic reasoning over NLP output and supplied DevControl evidence."""

import hashlib
import json

from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.nlp import NexusNlpInterpreter
from nexus_ai.fundamentals import FundamentalsKnowledgeBase

from .models import ReasoningFinding, ReasoningRequest, ReasoningResult


class NexusReasoner:
    """Produces conclusions only from supplied evidence; never executes actions."""

    def __init__(self, interpreter: NexusNlpInterpreter | None = None, ml_pipeline=None):
        self.interpreter = interpreter or NexusNlpInterpreter()
        self.ml_pipeline = ml_pipeline
        self.fundamentals = FundamentalsKnowledgeBase()

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
        fundamentals = self.fundamentals.recognize(
            str(request.context.get("code", "")),
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El código muestra evidencia de {item.concept_id}.",
                [{"source": "computer_science_fundamentals", **item.to_dict()}],
                item.confidence,
            )
            for item in fundamentals
        )
        paradigms = self.fundamentals.recognize_paradigms(
            str(request.context.get("code", "")),
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El código muestra evidencia del paradigma {item.paradigm_id}.",
                [{"source": "programming_paradigms", **item.to_dict()}],
                item.confidence,
            )
            for item in paradigms
        )
        oop_analysis = self.fundamentals.analyze_oop(
            str(request.context.get("code", "")),
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El análisis OO reporta {item.principle}: {item.status}.",
                [{"source": "advanced_object_oriented_programming", **item.to_dict()}],
                0.8 if item.status == "observed" else 0.7,
            )
            for item in oop_analysis.findings
        )
        pattern_analysis = self.fundamentals.analyze_patterns(
            str(request.context.get("code", "")),
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El análisis de patrones reporta {item.pattern_id}: {item.status}.",
                [{"source": "design_patterns", **item.to_dict()}],
                0.8 if item.status == "observed" else 0.7,
            )
            for item in pattern_analysis.recommendations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El código contiene evidencia del patrón {item.pattern_id}.",
                [{"source": "design_patterns", **item.to_dict()}],
                item.confidence,
            )
            for item in pattern_analysis.observations
        )
        project_files = request.context.get("project_files")
        architecture_input = project_files if isinstance(project_files, dict) else str(
            request.context.get("code", "")
        )
        architecture_analysis = self.fundamentals.analyze_architecture(
            architecture_input,
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El proyecto muestra evidencia de la arquitectura {item.architecture_id}.",
                [{"source": "software_architecture", **item.to_dict()}],
                item.confidence,
            )
            for item in architecture_analysis.observations
        )
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
        if fundamentals:
            steps.append("Reconocí patrones de fundamentos de computación mediante AST y los mantuve como observaciones.")
        if paradigms:
            steps.append("Reconocí paradigmas de programación mediante AST y los mantuve como observaciones.")
        if oop_analysis.findings:
            steps.append("Revisé principios OO, contratos, cohesión y acoplamiento con reglas estáticas conservadoras.")
        if pattern_analysis.observations or pattern_analysis.recommendations:
            steps.append("Reconocí patrones de diseño y separé patrones observados de recomendaciones condicionales.")
        if architecture_analysis.observations:
            steps.append("Analicé la arquitectura a partir de rutas, límites y vocabulario presentes en el proyecto.")
        protocol_input = project_files if isinstance(project_files, dict) else str(
            request.context.get("code", "")
        )
        protocol_observations = self.fundamentals.recognize_protocols(
            protocol_input,
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El código muestra evidencia del protocolo {item.protocol_id}.",
                [{"source": "api_protocols", **item.to_dict()}],
                item.confidence,
            )
            for item in protocol_observations
        )
        communication_diagnoses = self.fundamentals.diagnose_communication(
            request.context.get("communication_trace", {})
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema de comunicación {item.issue}.",
                [{"source": "api_protocols", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in communication_diagnoses
        )
        if protocol_observations or communication_diagnoses:
            steps.append("Analicé protocolos, contratos HTTP y evidencia de comunicación frontend-backend.")
        database_input = project_files if isinstance(project_files, dict) else str(
            request.context.get("code", "")
        )
        database_analysis = self.fundamentals.analyze_database(
            database_input,
            str(request.context.get("language", "python")),
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"La evidencia de datos muestra {item.database_id}.",
                [{"source": "database_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in database_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema de base de datos {item.issue}.",
                [{"source": "database_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in database_analysis.findings
        )
        if database_analysis.observations or database_analysis.findings:
            steps.append("Analicé queries, ORM, integridad y rendimiento de base de datos sin ejecutar consultas.")
        git_input = request.context.get("git_repository", request.context.get("git", {}))
        git_analysis = self.fundamentals.analyze_git(git_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El historial Git muestra evidencia de {item.git_id}.",
                [{"source": "git_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in git_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema Git {item.issue}.",
                [{"source": "git_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in git_analysis.findings
        )
        if git_analysis.observations or git_analysis.findings:
            steps.append("Analicé branches, commits, diffs, conflictos y trazabilidad de cambios sin ejecutar operaciones Git.")
        testing_input = request.context.get("test_files", request.context.get("tests", {}))
        testing_analysis = self.fundamentals.analyze_tests(testing_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"La prueba valida el comportamiento: {item.behavior}.",
                [{"source": "testing_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in testing_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema de testing {item.issue}.",
                [{"source": "testing_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in testing_analysis.findings
        )
        if testing_analysis.observations or testing_analysis.findings:
            steps.append("Analicé tipos de pruebas, dobles, assertions y el comportamiento validado sin ejecutar la suite.")
        html_input = request.context.get("html_files", request.context.get("html", {}))
        html_analysis = self.fundamentals.analyze_html(html_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El HTML muestra evidencia de {item.html_id}.",
                [{"source": "html_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in html_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema HTML {item.issue}.",
                [{"source": "html_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in html_analysis.findings
        )
        if html_analysis.observations or html_analysis.findings:
            steps.append("Analicé estructura, semántica, accesibilidad, metadata y SEO del HTML sin renderizarlo.")
        css_input = request.context.get("css_files", request.context.get("css", {}))
        css_analysis = self.fundamentals.analyze_css(css_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El CSS muestra evidencia de {item.css_id}.",
                [{"source": "css_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in css_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema CSS {item.issue}.",
                [{"source": "css_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in css_analysis.findings
        )
        if css_analysis.observations or css_analysis.findings:
            steps.append("Analicé selectores, cascada, layout y responsive del CSS sin renderizarlo.")
        javascript_input = request.context.get("javascript_files", request.context.get("javascript", {}))
        javascript_analysis = self.fundamentals.analyze_javascript(javascript_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"JavaScript muestra evidencia de {item.javascript_id}.",
                [{"source": "javascript_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in javascript_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema JavaScript {item.issue}.",
                [{"source": "javascript_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in javascript_analysis.findings
        )
        if javascript_analysis.observations or javascript_analysis.findings:
            steps.append("Analicé JavaScript, asincronía, DOM, eventos y manejo de errores sin ejecutar el código.")
        php_input = request.context.get("php_files", request.context.get("php", {}))
        php_analysis = self.fundamentals.analyze_php(php_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"PHP muestra evidencia de {item.php_id}.",
                [{"source": "php_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in php_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema PHP {item.issue}.",
                [{"source": "php_intelligence", **item.to_dict()}],
                0.9 if item.severity in {"critical", "high"} else 0.75,
            )
            for item in php_analysis.findings
        )
        if php_analysis.observations or php_analysis.findings:
            steps.append("Analicé sintaxis PHP, tipos, orientación a objetos, excepciones, persistencia, sesiones y seguridad sin ejecutar el código.")
        advanced_php_input = request.context.get("advanced_php_files", request.context.get("advanced_php", php_input))
        advanced_php_analysis = self.fundamentals.analyze_advanced_php(advanced_php_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"PHP avanzado muestra evidencia de {item.php_id}.",
                [{"source": "advanced_php_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in advanced_php_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema avanzado de PHP {item.issue}.",
                [{"source": "advanced_php_intelligence", **item.to_dict()}],
                0.9 if item.severity in {"critical", "high"} else 0.75,
            )
            for item in advanced_php_analysis.findings
        )
        if advanced_php_analysis.observations or advanced_php_analysis.findings:
            steps.append("Analicé memoria, Composer, PSR, SPL, streams, procesos, CLI, entorno, OPcache y rendimiento de PHP.")
        browser_input = request.context.get("browser_files", request.context.get("browser", {}))
        browser_analysis = self.fundamentals.analyze_browser(browser_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"El código muestra evidencia de comportamiento de navegador: {item.browser_id}.",
                [{"source": "browser_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in browser_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema específico del navegador {item.issue}.",
                [{"source": "browser_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in browser_analysis.findings
        )
        if browser_analysis.observations or browser_analysis.findings:
            steps.append("Analicé DOM, rendering, event loop, storage, red, CORS, seguridad y lifecycle sin ejecutar el navegador.")
        tailwind_input = request.context.get("tailwind_files", request.context.get("tailwind", {}))
        tailwind_analysis = self.fundamentals.analyze_tailwind(tailwind_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se reconoció el patrón Tailwind {item.tailwind_id}.",
                [{"source": "tailwind_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in tailwind_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema Tailwind {item.issue}.",
                [{"source": "tailwind_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in tailwind_analysis.findings
        )
        if tailwind_analysis.observations or tailwind_analysis.findings:
            steps.append("Interpreté utilidades Tailwind, variantes, layout, estados, tema y configuración sin renderizar la interfaz.")
        bootstrap_input = request.context.get("bootstrap_files", request.context.get("bootstrap", {}))
        bootstrap_analysis = self.fundamentals.analyze_bootstrap(bootstrap_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se reconoció la estructura Bootstrap {item.bootstrap_id}.",
                [{"source": "bootstrap_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in bootstrap_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema Bootstrap {item.issue}.",
                [{"source": "bootstrap_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in bootstrap_analysis.findings
        )
        if bootstrap_analysis.observations or bootstrap_analysis.findings:
            steps.append("Reconocí Bootstrap, su grid, componentes, utilidades y variantes responsive sin renderizar la interfaz.")
        react_input = request.context.get("react_files", request.context.get("react", {}))
        react_analysis = self.fundamentals.analyze_react(react_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"React muestra evidencia de {item.react_id}.",
                [{"source": "react_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in react_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema React {item.issue}.",
                [{"source": "react_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in react_analysis.findings
        )
        if react_analysis.observations or react_analysis.findings:
            steps.append("Analicé componentes React, JSX, estado, hooks, efectos, rendering, routing y arquitectura sin ejecutar la aplicación.")
        vue_input = request.context.get("vue_files", request.context.get("vue", {}))
        vue_analysis = self.fundamentals.analyze_vue(vue_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Vue muestra evidencia de {item.vue_id}.",
                [{"source": "vue_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in vue_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema Vue {item.issue}.",
                [{"source": "vue_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in vue_analysis.findings
        )
        if vue_analysis.observations or vue_analysis.findings:
            steps.append("Analicé componentes Vue, reactividad, APIs, lifecycle, routing y stores sin ejecutar la aplicación.")
        quasar_input = request.context.get("quasar_files", request.context.get("quasar", {}))
        quasar_analysis = self.fundamentals.analyze_quasar(quasar_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Quasar muestra evidencia de {item.quasar_id}.",
                [{"source": "quasar_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in quasar_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el problema Quasar {item.issue}.",
                [{"source": "quasar_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in quasar_analysis.findings
        )
        if quasar_analysis.observations or quasar_analysis.findings:
            steps.append("Analicé estructura Quasar, componentes Q, modos de build, boot files, routing y plugins sin ejecutar Quasar.")
        frontend_input = request.context.get("frontend_architecture_files", request.context.get("frontend_architecture", {}))
        frontend_analysis = self.fundamentals.analyze_frontend_architecture(frontend_input)
        findings.extend(
            ReasoningFinding(
                "observation",
                f"La arquitectura frontend muestra evidencia de {item.architecture_id}.",
                [{"source": "frontend_architecture_intelligence", **item.to_dict()}],
                item.confidence,
            )
            for item in frontend_analysis.observations
        )
        findings.extend(
            ReasoningFinding(
                "observation",
                f"Se detectó el riesgo arquitectónico frontend {item.issue}.",
                [{"source": "frontend_architecture_intelligence", **item.to_dict()}],
                0.85 if item.severity == "high" else 0.75,
            )
            for item in frontend_analysis.findings
        )
        if frontend_analysis.observations or frontend_analysis.findings:
            steps.append("Expliqué la arquitectura frontend por componentes, estado, rutas, APIs, formularios, seguridad, resiliencia, caché y responsive.")
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
