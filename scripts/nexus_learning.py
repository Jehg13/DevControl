#!/usr/bin/env python3
"""Operator CLI for the explicit Nexus continuous-learning lifecycle."""
from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parents[1]))
from nexus_learning import ContinuousLearningSystem


def main() -> None:
    parser = argparse.ArgumentParser(description="Record, validate, publish, and schedule Nexus learning data.")
    parser.add_argument("--store", required=True, help="learning store directory")
    subparsers = parser.add_subparsers(dest="command", required=True)

    record = subparsers.add_parser("record")
    record.add_argument("problem")
    record.add_argument("solution")
    record.add_argument("--category")
    record.add_argument("--tag", action="append", default=[])

    evaluate = subparsers.add_parser("evaluate")
    evaluate.add_argument("experience_id")
    evaluate.add_argument("--success", action="store_true")
    evaluate.add_argument("--score", type=float)
    evaluate.add_argument("--evidence", action="append", default=[])

    validate = subparsers.add_parser("validate")
    validate.add_argument("experience_id")
    validate.add_argument("--reviewer", default="operator")
    validate.add_argument("--quality-score", type=float)
    validate.add_argument("--tests-failed", action="store_true")
    validate.add_argument("--regression", action="store_true")
    validate.add_argument("--reject", action="store_true")

    dataset = subparsers.add_parser("dataset")
    dataset.add_argument("output")
    dataset.add_argument("--version")
    dataset.add_argument("--approve-by")

    approve = subparsers.add_parser("approve-dataset")
    approve.add_argument("version")
    approve.add_argument("--by", default="operator")

    select = subparsers.add_parser("select")
    select.add_argument("query")
    select.add_argument("--category")
    select.add_argument("--limit", type=int, default=8)

    schedule = subparsers.add_parser("schedule")
    schedule.add_argument("dataset_version")
    schedule.add_argument("--run-at")
    schedule.add_argument("--interval-days", type=int)

    args = parser.parse_args()
    system = ContinuousLearningSystem(args.store)
    if args.command == "record":
        result = system.record_experience(args.problem, args.solution, category=args.category, tags=args.tag)
    elif args.command == "evaluate":
        result = system.evaluate_outcome(args.experience_id, success=args.success, score=args.score, evidence=args.evidence)
    elif args.command == "validate":
        result = system.validate_solution(
            args.experience_id,
            approved=not args.reject,
            tests_passed=not args.tests_failed,
            regression_free=not args.regression,
            quality_score=args.quality_score,
            reviewer=args.reviewer,
        )
    elif args.command == "dataset":
        result = system.create_dataset_version(args.output, version=args.version)
        if args.approve_by:
            result = system.approve_dataset(result.version, approved_by=args.approve_by)
    elif args.command == "approve-dataset":
        result = system.approve_dataset(args.version, approved_by=args.by)
    elif args.command == "select":
        result = system.select_relevant_examples(args.query, category=args.category, limit=args.limit)
    else:
        result = system.schedule_training(args.dataset_version, run_at=args.run_at, interval_days=args.interval_days)
    if isinstance(result, list):
        result = [getattr(item, "__dict__", item) for item in result]
    else:
        result = getattr(result, "__dict__", result)
    print(json.dumps(result, ensure_ascii=True, indent=2))


if __name__ == "__main__":
    main()
