"""Bounded inference over an already loaded local Nexus model."""

from .loader import LocalModel


class InferenceEngine:
    def __init__(self, model: LocalModel) -> None:
        self.model = model

    def generate(self, message: str, context: dict | None = None) -> dict:
        if not isinstance(message, str) or not message.strip():
            raise ValueError("message must be a non-empty string")
        context = context if isinstance(context, dict) else {}
        prompt = self._prompt(message, context)
        result = self.model.generate(prompt)
        if not isinstance(result, dict) or not isinstance(result.get("text"), str):
            raise ValueError("el modelo local devolvió una inferencia inválida")
        return result

    @staticmethod
    def _prompt(message: str, context: dict) -> str:
        return (
            "<|system|>Nexus es un asistente local de ingeniería. "
            "Responde únicamente con base en el mensaje y contexto proporcionados."
            "<|user|>"
            + message
            + "\n<|context|>"
            + __import__("json").dumps(context, ensure_ascii=False, sort_keys=True)
            + "<|assistant|>"
        )
