"""Application facade. It intentionally performs no model or tool execution."""

from .contracts import Interpretation, NexusAiRequest, NexusAiResponse
from nexus_ai.tools import NexusLaravelToolBridge, ToolProposal
from nexus_ai.knowledge import KnowledgeGraph
from nexus_ai.memory import ContextManager
from nexus_ai.planning import NexusPlanner, PlanRequest
from nexus_ai.reasoning import NexusReasoner, ReasoningRequest
from nexus_ai.responses import NexusResponseGenerator, ResponseInput


class NexusAiApplication:
    """Stable future entry point called by an adapter owned by Laravel."""

    def handle(self, request: NexusAiRequest) -> NexusAiResponse:
        return NexusAiResponse(
            interpretation=Interpretation(
                entities={"message": request.message},
            ),
            context_used=request.context.values,
            response="La fundación de Nexus AI está preparada, pero la inteligencia aún no está implementada.",
            status="not_implemented",
            tool_information=[
                {
                    "name": tool.name,
                    "permissions": tool.permissions,
                    "requires_confirmation": tool.requires_confirmation,
                }
                for tool in request.tools
            ],
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
        context_manager = ContextManager()
        context_manager.remember_turn(session_id, "user", request.message)
        snapshot = context_manager.snapshot(session_id, request.message)
        graph = KnowledgeGraph.from_devcontrol(knowledge_data) if knowledge_data else None
        reasoning = NexusReasoner().analyze(
            ReasoningRequest(request.message, data, snapshot.to_dict()),
            graph,
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
                conversation=[{"role": turn.role, "content": turn.content} for turn in request.conversation],
            )
        )
        return NexusAiResponse(
            interpretation=Interpretation(
                intent=reasoning.interpretation.get("intent", "unclassified"),
                entities=reasoning.interpretation.get("entities", {}),
                confidence=reasoning.interpretation.get("confidence", "low"),
            ),
            context_used=snapshot.to_dict(),
            plan=[step.to_dict() for step in plan_result.steps],
            proposed_actions=[],
            response=rendered.text,
            status=rendered.status,
            tool_information=[
                {
                    "name": tool.name,
                    "permissions": tool.permissions,
                    "requires_confirmation": tool.requires_confirmation,
                }
                for tool in request.tools
            ],
            errors=reasoning.information_needed,
        )
