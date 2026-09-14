#!/usr/bin/env python3
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_training import TrainingConfig, train


def main() -> None:
    parser = argparse.ArgumentParser(description="Train the dependency-free Nexus AI prototype.")
    parser.add_argument("dataset")
    parser.add_argument("--output", required=True)
    parser.add_argument("--vocab-size", type=int, required=True)
    parser.add_argument("--hidden-size", type=int, default=32)
    parser.add_argument("--epochs", type=int, default=3)
    parser.add_argument("--learning-rate", type=float, default=0.05)
    parser.add_argument("--eval-interval", type=int, default=10)
    parser.add_argument("--checkpoint-interval", type=int, default=25)
    parser.add_argument("--seed", type=int, default=1337)
    parser.add_argument("--resume")
    args = parser.parse_args()
    if args.eval_interval < 1 or args.checkpoint_interval < 1:
        parser.error("evaluation and checkpoint intervals must be positive")
    summary = train(
        args.dataset,
        args.output,
        args.vocab_size,
        TrainingConfig(
            seed=args.seed,
            hidden_size=args.hidden_size,
            learning_rate=args.learning_rate,
            epochs=args.epochs,
            eval_interval=args.eval_interval,
            checkpoint_interval=args.checkpoint_interval,
        ),
        resume=args.resume,
    )
    print(json.dumps(summary, indent=2))


if __name__ == "__main__":
    main()
