"""Train the local Nexus classifiers from the versioned DevControl datasets."""

import json
from pathlib import Path

from .pipeline import NexusMlPipeline


if __name__ == "__main__":
    root = Path(__file__).parents[1]
    datasets = root / "datasets"
    model_path = root / "models" / "v1.0.0" / "nexus_ml.json"
    report = NexusMlPipeline().train(
        datasets / "devcontrol_v1_train.jsonl",
        datasets / "devcontrol_v1_validation.jsonl",
        model_path=model_path,
    )
    print(json.dumps(report.to_dict(), ensure_ascii=False, indent=2))
