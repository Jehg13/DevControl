# Fase 40: aprendizaje continuo controlado

Nexus registra una experiencia resuelta, pero **registrar no significa
entrenar**. `nexus_learning` implementa un flujo explícito:

```text
resolver problema
  -> record_experience (recorded)
  -> evaluate_outcome (evaluated)
  -> validate_solution (validated/rejected)
  -> create_dataset_version (pending_approval)
  -> aprobación humana del dataset
  -> experimento y métricas
  -> quality gates
  -> candidato de modelo -> aprobación -> modelo activo
```

Las experiencias rechazadas, con regresiones o sin evidencia nunca se incluyen
en un dataset. La creación de un dataset tampoco inicia entrenamiento. Las
versiones de dataset, experimentos y modelos se guardan en un
`learning-state.json` auditable. El modelo activo puede restaurarse con
`rollback`.

## Uso desde Python

```python
from nexus_learning import ContinuousLearningSystem

learning = ContinuousLearningSystem("artifacts/nexus-learning")
experience = learning.record_experience(
    "La consulta devuelve filas duplicadas",
    "Se añadió una restricción única y una prueba de regresión",
    project_id="devcontrol",
)
learning.evaluate_outcome(experience.experience_id, success=True, score=0.95,
                          evidence=["pytest: 42 passed"])
learning.validate_solution(experience.experience_id, reviewer="alice")
dataset = learning.create_dataset_version("artifacts/experience-dataset")
learning.approve_dataset(dataset.version, approved_by="alice")
experiment = learning.start_experiment(dataset.version, config={"seed": 1337})
learning.complete_experiment(experiment.experiment_id, {"evaluation_score": 0.91})
model = learning.register_model_version(
    experiment.experiment_id,
    artifact="artifacts/run/latest.json",
    metrics={"evaluation_score": 0.91, "regression_rate": 0.0},
)
learning.approve_model(model.version, approved_by="alice")
```

`start_experiment` solo acepta datasets aprobados y `approve_model` exige las
compuertas de calidad. Por defecto se exige score de resultado y validación de
0.70, al menos un ejemplo, evaluación de 0.70 y cero regresiones. Se pueden
pasar otros `QualityGates` al constructor.

## Selección y programación

`select_relevant_examples(query)` usa recuperación léxica determinista y solo
devuelve experiencias validadas. `schedule_training` registra un ciclo futuro
para un dataset ya aprobado; `due_training_cycles` solo informa qué ciclos
están vencidos. La ejecución sigue siendo una acción explícita del operador o
del orquestador autorizado: no hay entrenamiento automático al registrar,
evaluar, validar o programar.

## CLI

```text
python scripts/nexus_learning.py --store artifacts/learning record "problema" "solución"
python scripts/nexus_learning.py --store artifacts/learning evaluate EXPERIENCE --success --score 0.9
python scripts/nexus_learning.py --store artifacts/learning validate EXPERIENCE --reviewer alice
python scripts/nexus_learning.py --store artifacts/learning dataset artifacts/dataset
python scripts/nexus_learning.py --store artifacts/learning approve-dataset DATASET --by alice
python scripts/nexus_learning.py --store artifacts/learning select "consulta duplicada"
python scripts/nexus_learning.py --store artifacts/learning schedule DATASET --interval-days 7
```

El dataset de experiencias puede recibir un `NexusTokenizer` en
`create_dataset_version(..., tokenizer=tokenizer)` para delegar la tokenización
y los shards a `nexus_dataset.DatasetBuilder`. En ambos casos el contenido se
deriva exclusivamente de experiencias validadas.
