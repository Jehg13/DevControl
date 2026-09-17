"""Local model health probe used by Laravel and shell diagnostics."""

import json
import os

from nexus_ai.models import ModelLoadError, ModelLoader
from nexus_inference import InferenceConfig


def main() -> int:
    try:
        loader = ModelLoader(
            os.getenv("NEXUS_AI_MODEL_PATH", "storage/app/nexus-model/latest.json"),
            os.getenv("NEXUS_AI_TOKENIZER_PATH", "storage/app/nexus-model/tokenizer.json"),
            InferenceConfig(timeout_seconds=float(os.getenv("NEXUS_LOCAL_TIMEOUT", "30"))),
        )
        report = loader.health()
        print(json.dumps(report, ensure_ascii=False))
        return 0 if report["inference_ready"] else 1
    except ModelLoadError as error:
        print(json.dumps({
            "enabled": True,
            "python": True,
            "model_loaded": False,
            "tokenizer_loaded": False,
            "inference_ready": False,
            "error": {"code": error.code, "message": str(error)},
        }, ensure_ascii=False))
        return 1


if __name__ == "__main__":
    raise SystemExit(main())
