"""Application facade for the execution-free Nexus intelligence pipeline."""

import os
from pathlib import Path

from .contracts import Interpretation, NexusAiRequest, NexusAiResponse
from nexus_ai.tools import NexusLaravelToolBridge, ToolProposal
from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.memory import ContextManager, ConversationMemoryStore
from nexus_ai.planning import NexusPlanner, PlanRequest
from nexus_ai.reasoning import NexusReasoner, ReasoningRequest
from nexus_ai.responses import NexusResponseGenerator, ResponseInput
from nexus_ai.grounding import GroundingValidator
from nexus_ai.semantic import SemanticQueryBuilder
from nexus_ai.models import InferenceEngine, ModelLoadError, ModelLoader
from nexus_inference import InferenceConfig


class NexusAiApplication:
    """Stable future entry point called by an adapter owned by Laravel."""

    def __init__(self, context_manager: ContextManager | None = None):
        self.context_manager = context_manager or ContextManager(
            ConversationMemoryStore(
                ttl_seconds=int(os.getenv("NEXUS_MEMORY_TTL", "1800")),
                max_records=int(os.getenv("NEXUS_MEMORY_MAX_RECORDS", "50")),
                path=os.getenv(
                    "NEXUS_MEMORY_PATH",
                    str(Path.cwd() / "storage" / "nexus_ai" / "memory.json"),
                ),
            )
        )
        self.inference_engine = None
        self.model_error = None
        if os.getenv("NEXUS_AI_ENABLED", "false").lower() in {"1", "true", "yes", "on"}:
            try:
                loader = ModelLoader(
                    os.getenv("NEXUS_AI_MODEL_PATH", "storage/app/nexus-model/latest.json"),
                    os.getenv("NEXUS_AI_TOKENIZER_PATH", "storage/app/nexus-model/tokenizer.json"),
                    InferenceConfig(
                        timeout_seconds=float(os.getenv("NEXUS_LOCAL_TIMEOUT", "30")),
                        max_new_tokens=int(os.getenv("NEXUS_LOCAL_MAX_NEW_TOKENS", "128")),
                    ),
                )
                self.inference_engine = InferenceEngine(loader.load())
            except ModelLoadError as error:
                self.model_error = {"code": error.code, "message": str(error)}

    def handle(self, request: NexusAiRequest) -> NexusAiResponse:
        values = request.context.values
        return self.process(
            request,
            recovered_data=values.get("data", values),
            execution_results=values.get("tool_results", []),
            session_id=str(request.metadata.get("session_id", "default")),
        )

    def propose_tool(
        self,
        request: NexusAiRequest,
        tool_name: str,
        parameters: dict | None = None,
    ) -> ToolProposal:
        """Create a Laravel-reviewed proposal; never execute the tool."""
        bridge = NexusLaravelToolBridge(
            [
                {
                    "name": tool.name,
                    "description": tool.description,
                    "parameters": tool.parameters,
                    "permissions": tool.permissions,
                    "requires_confirmation": tool.requires_confirmation,
                }
                for tool in request.tools
            ]
        )
        return bridge.propose(tool_name, parameters)

    def process(
        self,
        request: NexusAiRequest,
        *,
        recovered_data: dict | None = None,
        knowledge_data: dict | None = None,
        execution_results: list[dict] | None = None,
        session_id: str = "default",
    ) -> NexusAiResponse:
        """Run the Python intelligence pipeline without executing Laravel tools."""
        data = recovered_data or {}
        context_manager = self.context_manager
        context_manager.remember_turn(session_id, "user", request.message)
        for entity, records in data.items():
            if isinstance(records, list):
                context_manager.remember_result(session_id, entity, records)
        snapshot = context_manager.snapshot(session_id, request.message)
        graph = KnowledgeGraph.from_devcontrol(knowledge_data) if knowledge_data else None
        reasoning = NexusReasoner().analyze(
            ReasoningRequest(request.message, data, snapshot.to_dict()),
            graph,
        )
        data_requests = self._data_requests(reasoning.interpretation, request)
        grounding = GroundingValidator().validate(
            reasoning.interpretation,
            reasoning.to_dict(),
            execution_results or [],
            recovered_data=data,
        )
        plan_result = NexusPlanner().create_plan(
            PlanRequest(
                request.message,
                data,
                request.permissions,
                [tool.__dict__ for tool in request.tools],
            )
        )
        rendered = NexusResponseGenerator().generate(
            ResponseInput(
                request.message,
                interpretation=reasoning.interpretation,
                context=snapshot.to_dict(),
                memory=snapshot.relevant_records,
                knowledge=graph.export()["assertions"] if graph else [],
                reasoning=reasoning.to_dict(),
                plan=[step.to_dict() for step in plan_result.steps],
                tool_results=execution_results or [],
                grounding=grounding.to_dict(),
                conversation=[{"role": turn.role, "content": turn.content} for turn in request.conversation],
            )
        )
        if self.model_error is not None:
            return NexusAiResponse(
                interpretation=Interpretation(
                    intent=reasoning.interpretation.get("intent", "unclassified"),
                    entities=reasoning.interpretation.get("entities", {}),
                    confidence=reasoning.interpretation.get("confidence", "low"),
                ),
                context_used=snapshot.to_dict(),
                plan=[step.to_dict() for step in plan_result.steps],
                response="",
                status="model_unavailable",
                errors=[self.model_error["message"]],
                data_requests=data_requests,
                evidence=grounding.evidence,
                grounding=grounding.to_dict(),
            )
        response_text = rendered.text
        response_status = rendered.status
        if self.inference_engine is not None:
            try:
                local_result = self.inference_engine.generate(
                    request.message,
                    {
                        "context": snapshot.to_dict(),
                        "reasoning": reasoning.to_dict(),
                        "plan": [step.to_dict() for step in plan_result.steps],
                        "evidence": grounding.evidence,
                    },
                )
            except (TimeoutError, ValueError, RuntimeError) as error:
                return NexusAiResponse(
                    interpretation=Interpretation(
                        intent=reasoning.interpretation.get("intent", "unclassified"),
                        entities=reasoning.interpretation.get("entities", {}),
                        confidence=reasoning.interpretation.get("confidence", "low"),
                    ),
                    context_used=snapshot.to_dict(),
                    plan=[step.to_dict() for step in plan_result.steps],
                    response="",
                    status="inference_failed",
                    errors=[str(error)],
                    data_requests=data_requests,
                    evidence=grounding.evidence,
                    grounding=grounding.to_dict(),
                )
            if not local_result["text"].strip():
                raise ValueError("el modelo local devolvió una respuesta vacía")
            response_text = local_result["text"]
            response_status = "completed"
        context_manager.remember_turn(session_id, "assistant", rendered.text, reasoning.interpretation)
        return NexusAiResponse(
            interpretation=Interpretation(
                intent=reasoning.interpretation.get("intent", "unclassified"),
                entities=reasoning.interpretation.get("entities", {}),
                confidence=reasoning.interpretation.get("confidence", "low"),
            ),
            context_used=snapshot.to_dict(),
            plan=[step.to_dict() for step in plan_result.steps],
            proposed_actions=[],
            response=response_text,
            status=response_status,
            tool_information=[
                {
                    "name": tool.name,
                    "permissions": tool.permissions,
                    "requires_confirmation": tool.requires_confirmation,
                }
                for tool in request.tools
            ],
            errors=reasoning.information_needed,
            data_requests=data_requests,
            evidence=grounding.evidence,
            grounding=grounding.to_dict(),
        )

    def _data_requests(self, interpretation: dict, request: NexusAiRequest) -> list[dict]:
        if request.metadata.get("data_resolved"):
            return []
        query = SemanticQueryBuilder().build(interpretation)
        if query is None:
            return []
        return [query.to_dict()]
