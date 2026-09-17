"""Stable schema and labels for the first DevControl dataset version."""

from typing import Any

DATASET_VERSION = "1.0.0"
REQUIRED_FIELDS = {"id", "version", "split", "input", "target"}
TARGET_FIELDS = {"intent", "entity", "action", "entities", "filters", "content", "needs_clarification"}
SPLITS = {"train", "validation"}
INTENTS = {
    "consultar_proyectos",
    "consultar_tareas",
    "consultar_bugs",
    "consultar_incidentes",
    "consultar_actualizaciones",
    "comparar_proyectos",
    "crear_tarea",
    "crear_bug",
    "crear_incidente",
    "crear_actualizacion",
    "modificar_tarea",
    "modificar_bug",
    "modificar_incidente",
    "modificar_actualizacion",
    "eliminar_tarea",
    "eliminar_bug",
    "eliminar_incidente",
    "eliminar_actualizacion",
    "analizar_codigo",
    "analizar_problema",
    "seguimiento_conversacion",
    "solicitar_aclaracion",
    "ambiguo",
}
ENTITIES = {"proyecto", "tarea", "bug", "incidente", "actualizacion", "usuario", "codigo", "conversacion", "general"}
ACTIONS = {"query", "create", "update", "delete", "analyze", "follow_up", "clarify", "unknown"}
ENTITY_FIELDS = {"name", "id", "title", "status", "priority", "project_id", "path", "text"}
FILTER_FIELDS = {"status", "priority", "project_id", "project_name", "search"}


def validate_record_shape(record: dict[str, Any]) -> list[str]:
    errors: list[str] = []
    missing = REQUIRED_FIELDS - record.keys()
    errors.extend(f"missing field: {field}" for field in sorted(missing))
    if missing:
        return errors
    if not isinstance(record["id"], str) or not record["id"].strip():
        errors.append("id must be a non-empty string")
    if record["version"] != DATASET_VERSION:
        errors.append(f"version must be {DATASET_VERSION}")
    if record["split"] not in SPLITS:
        errors.append(f"split must be one of {sorted(SPLITS)}")
    if not isinstance(record["input"], str) or not record["input"].strip():
        errors.append("input must be a non-empty string")
    target = record["target"]
    if not isinstance(target, dict):
        return errors + ["target must be an object"]
    target_missing = TARGET_FIELDS - target.keys()
    errors.extend(f"missing target field: {field}" for field in sorted(target_missing))
    if target_missing:
        return errors
    if target["intent"] not in INTENTS:
        errors.append(f"unknown intent: {target['intent']}")
    if target["entity"] not in ENTITIES:
        errors.append(f"unknown entity: {target['entity']}")
    if target["action"] not in ACTIONS:
        errors.append(f"unknown action: {target['action']}")
    if not isinstance(target["entities"], dict) or not set(target["entities"]) <= ENTITY_FIELDS:
        errors.append("entities contains unsupported fields")
    if not isinstance(target["filters"], dict) or not set(target["filters"]) <= FILTER_FIELDS:
        errors.append("filters contains unsupported fields")
    if not isinstance(target["needs_clarification"], bool):
        errors.append("needs_clarification must be boolean")
    if target["action"] == "clarify" and not target["needs_clarification"]:
        errors.append("clarify actions must require clarification")
    if target["action"] != "clarify" and target["needs_clarification"] and target["intent"] not in {"ambiguo", "solicitar_aclaracion"}:
        errors.append("only ambiguous or clarification intents may require clarification")
    return errors
