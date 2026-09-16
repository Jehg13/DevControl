"""Provider-independent intelligence seam for a later phase."""

from typing import Protocol

from nexus_ai.api.contracts import NexusAiRequest, NexusAiResponse


class IntelligenceEngine(Protocol):
    def interpret(self, request: NexusAiRequest) -> NexusAiResponse:
        """Interpret a request without granting execution authority."""
