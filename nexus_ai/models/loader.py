"""Validated, local-only model loading for the Nexus AI process."""

import json
from dataclasses import dataclass
from pathlib import Path

from nexus_inference import InferenceConfig, LocalInferenceRuntime
from nexus_tokenizer import NexusTokenizer
from nexus_training import load_checkpoint


class ModelLoadError(RuntimeError):
    """Raised when a local Nexus artifact cannot be loaded."""

    def __init__(self, code: str, message: str) -> None:
        super().__init__(message)
        self.code = code


@dataclass
class LocalModel:
    """Loaded tokenizer and causal model kept alive by the Python process."""

    runtime: LocalInferenceRuntime
    checkpoint: Path
    tokenizer_path: Path

    @property
    def vocab_size(self) -> int:
        return self.runtime.model.vocab_size

    def generate(self, prompt: str) -> dict:
        return self.runtime.generate(prompt)


class ModelLoader:
    """Loads and validates one local model for the lifetime of an application."""

    def __init__(self, checkpoint: str | Path, tokenizer: str | Path, config: InferenceConfig | None = None) -> None:
        self.checkpoint = self._resolve(checkpoint, "model")
        self.tokenizer_path = self._resolve(tokenizer, "tokenizer")
        self.config = config or InferenceConfig()
        self._model: LocalModel | None = None

    @staticmethod
    def _resolve(value: str | Path, artifact: str) -> Path:
        path = Path(value)
        if not path.is_file():
            raise ModelLoadError(
                f"{artifact}_missing",
                f"El {'modelo' if artifact == 'model' else artifact} local de Nexus no existe: {path}",
            )
        return path

    def load(self) -> LocalModel:
        if self._model is not None:
            return self._model
        try:
            tokenizer = NexusTokenizer.load(self.tokenizer_path)
            model = load_checkpoint(self.checkpoint)
            if model.vocab_size != tokenizer.vocab_size:
                raise ModelLoadError(
                    "vocabulary_mismatch",
                    "El checkpoint y el tokenizer tienen vocabularios incompatibles.",
                )
            runtime = LocalInferenceRuntime(
                self.checkpoint,
                self.tokenizer_path,
                self.config,
                loaded_model=model,
                loaded_tokenizer=tokenizer,
            )
        except ModelLoadError:
            raise
        except (OSError, ValueError, KeyError, TypeError, json.JSONDecodeError) as error:
            raise ModelLoadError("model_load_failed", f"No se pudo cargar el modelo local de Nexus: {error}") from error
        self._model = LocalModel(runtime, self.checkpoint, self.tokenizer_path)
        return self._model

    def health(self) -> dict:
        status = {
            "enabled": True,
            "python": True,
            "model_path": str(self.checkpoint),
            "tokenizer_path": str(self.tokenizer_path),
            "model_exists": self.checkpoint.is_file(),
            "tokenizer_exists": self.tokenizer_path.is_file(),
            "model_loaded": self._model is not None,
            "tokenizer_loaded": self._model is not None,
            "inference_ready": self._model is not None,
        }
        if self._model is None:
            try:
                self.load()
            except ModelLoadError as error:
                status.update({"inference_ready": False, "error": {"code": error.code, "message": str(error)}})
                return status
        status.update({"model_loaded": True, "tokenizer_loaded": True, "inference_ready": True})
        return status
