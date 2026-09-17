"""Evidence-grounded technical reasoning dataset and evaluation."""

from .technical import ReasoningExample, TechnicalReasoning, evaluate_reasoning
from .investigation import TechnicalInvestigationEngine, InvestigationEvidence, InvestigationResult
from .proposals import SolutionProposal, SolutionProposalEngine, SolutionProposalResult
from .planning import EngineeringPlan, EngineeringPlanningEngine, EngineeringStep, PlanValidation

__all__ = [
    "ReasoningExample", "TechnicalReasoning", "evaluate_reasoning",
    "TechnicalInvestigationEngine", "InvestigationEvidence", "InvestigationResult",
    "SolutionProposal", "SolutionProposalEngine", "SolutionProposalResult",
    "EngineeringPlan", "EngineeringPlanningEngine", "EngineeringStep", "PlanValidation",
]
