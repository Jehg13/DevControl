#!/usr/bin/env python3
"""Reproducible hardware/model sizing report for Nexus AI.

This intentionally uses only the standard library. It estimates memory from
the actual checkpoint format and reports conservative budgets for future
decoder-only models; it does not claim that hardware creates model quality.
"""
from __future__ import annotations

import argparse
import json
import os
import platform
import shutil
import subprocess
from pathlib import Path


ROOT = Path(__file__).resolve().parents[1]


def gib(value: int) -> float:
    return round(value / (1024 ** 3), 2)


def artifact_info(path: Path) -> dict:
    if not path.is_file():
        return {"exists": False, "path": str(path), "bytes": 0}
    return {"exists": True, "path": str(path), "bytes": path.stat().st_size, "gib": gib(path.stat().st_size)}


def checkpoint_parameters(path: Path) -> int | None:
    if not path.is_file():
        return None
    payload = json.loads(path.read_text(encoding="utf-8"))
    model = payload.get("model", {})
    vocab = int(model.get("vocab_size", 0))
    hidden = int(model.get("hidden_size", 0))
    return vocab * hidden * 2 if vocab and hidden else None


def dataset_bytes(path: Path) -> int:
    return sum(file.stat().st_size for file in path.glob("**/*") if file.is_file())


def windows_hardware() -> dict:
    if platform.system() != "Windows":
        return {"cpu": platform.processor(), "logical_cpus": os.cpu_count()}
    command = (
        "Get-CimInstance Win32_Processor | Select-Object -First 1 Name,NumberOfCores,NumberOfLogicalProcessors;"
        "Get-CimInstance Win32_ComputerSystem | Select-Object TotalPhysicalMemory;"
        "Get-CimInstance Win32_VideoController | Select-Object Name,AdapterRAM"
    )
    try:
        output = subprocess.check_output(["powershell", "-NoProfile", "-Command", command], text=True)
    except (OSError, subprocess.CalledProcessError):
        return {"cpu": platform.processor(), "logical_cpus": os.cpu_count()}
    return {"raw": output.strip(), "logical_cpus": os.cpu_count()}


def profiles() -> list[dict]:
    return [
        {
            "name": "hardware actual",
            "ram_gib": 10.8, "vram_gib": 0, "storage_gib": 20,
            "cpu": "Ryzen 7 5700U / 8C-16T o equivalente",
            "power_w": "15-35 sostenidos", "cooling": "portátil; no ampliar TDP",
            "reasonable_model": "micro-modelo actual; hasta ~50M parámetros en CPU con cuantización y contexto corto",
            "training": "dataset pequeño, tokenizer y pruebas; no fine-tuning grande",
        },
        {
            "name": "upgrade económico",
            "ram_gib": 32, "vram_gib": 8, "storage_gib": 100,
            "cpu": "6-8 núcleos de escritorio", "power_w": "250-400 del sistema",
            "cooling": "torre con disipador y flujo frontal/trasero",
            "reasonable_model": "3B-7B cuantizado para inferencia; fine-tuning ligero con LoRA según backend",
            "training": "datasets pequeños y experimentos; no entrenamiento desde cero de 7B",
        },
        {
            "name": "workstation intermedia",
            "ram_gib": 64, "vram_gib": 16, "storage_gib": 500,
            "cpu": "8-16 núcleos", "power_w": "450-650 del sistema",
            "cooling": "disipador de torre o líquida AIO y fuente con margen",
            "reasonable_model": "7B-13B cuantizado; 3B-7B para fine-tuning controlado",
            "training": "LoRA/QLoRA y datasets medianos; no preentrenamiento serio",
        },
        {
            "name": "workstation avanzada",
            "ram_gib": 128, "vram_gib": 24, "storage_gib": 2000,
            "cpu": "16-32 núcleos", "power_w": "800-1200 del sistema",
            "cooling": "torre grande, flujo dedicado y fuente 80+ Gold/Platinum",
            "reasonable_model": "13B-34B cuantizado; 7B-13B para fine-tuning más cómodo",
            "training": "fine-tuning y evaluación; no garantiza calidad superior",
        },
        {
            "name": "servidor de entrenamiento",
            "ram_gib": 256, "vram_gib": 80, "storage_gib": 4000,
            "cpu": "32-64 núcleos", "power_w": "1500-3000 del sistema",
            "cooling": "sala/servidor con aire acondicionado y fuente redundante",
            "reasonable_model": "34B-70B con cuantización; modelos mayores requieren varias GPUs",
            "training": "fine-tuning grande y experimentos distribuidos; preentrenar desde cero sigue siendo costoso",
        },
    ]


def main() -> None:
    parser = argparse.ArgumentParser(description="Genera un informe reproducible de infraestructura Nexus.")
    parser.add_argument("--checkpoint", default="storage/app/nexus-model/latest.json")
    parser.add_argument("--tokenizer", default="storage/app/nexus-model/tokenizer.json")
    parser.add_argument("--dataset", default="storage/app/nexus-dataset")
    parser.add_argument("--json", action="store_true")
    args = parser.parse_args()

    checkpoint = ROOT / args.checkpoint
    tokenizer = ROOT / args.tokenizer
    dataset = ROOT / args.dataset
    parameters = checkpoint_parameters(checkpoint)
    report = {
        "host": windows_hardware(),
        "python": platform.python_version(),
        "artifacts": {
            "checkpoint": artifact_info(checkpoint),
            "tokenizer": artifact_info(tokenizer),
            "dataset": {"exists": dataset.is_dir(), "bytes": dataset_bytes(dataset) if dataset.is_dir() else 0},
        },
        "actual_model_parameters": parameters,
        "profiles": profiles(),
        "assumptions": {
            "inference_ram": "weights + tokenizer + runtime + 20% safety margin",
            "training_vram": "parameters * bytes_per_parameter + gradients + optimizer; practical budget is 4-8x weights",
            "storage": "at least 3 checkpoints plus dataset, logs and temporary files",
            "quality": "hardware changes throughput and feasible size, not training data quality or architecture",
        },
    }
    if args.json:
        print(json.dumps(report, indent=2, ensure_ascii=True))
        return
    print("Nexus AI infrastructure report")
    print(f"Host: {report['host'].get('raw', report['host'])}")
    print(f"Checkpoint: {report['artifacts']['checkpoint']}")
    print(f"Tokenizer: {report['artifacts']['tokenizer']}")
    print(f"Dataset bytes: {report['artifacts']['dataset']['bytes']}")
    print("Profiles:")
    for profile in report["profiles"]:
        print(f"- {profile['name']}: {profile['ram_gib']} GiB RAM, {profile['vram_gib']} GiB VRAM; {profile['reasonable_model']}")


if __name__ == "__main__":
    main()
