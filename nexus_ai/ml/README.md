# Nexus ML foundation

This phase uses a local multinomial Naive Bayes implementation with token and
bigram features. It trains four independent classifiers from the checked-in
DevControl dataset:

- intent;
- action;
- entity;
- clarification requirement.

The model only returns classifications. It cannot execute tools, access
Laravel, grant permissions, or bypass `NexusSecurityBoundary`.

The current datasets contain training and validation splits but no independent
test split. The pipeline reports validation metrics and explicitly reports the
test set as unavailable instead of reusing validation data.

Train and persist the versioned model:

```text
python -m nexus_ai.ml.train
```
