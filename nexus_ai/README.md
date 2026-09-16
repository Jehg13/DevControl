# Nexus AI foundation

This package is the Python boundary for future Nexus intelligence. It does not
connect to a model provider, execute tools, manage permissions, or store
memory.

Laravel remains the system of record and execution authority. A future adapter
may serialize `NexusAiRequest` from `NexusRuntimeRequest` and return
`NexusAiResponse` to Laravel. The Python layer must never bypass
`NexusToolRegistry`, `NexusPermissionManager`, or `NexusSecurityBoundary`.

Run the foundation tests from the project root:

```text
python -m unittest discover -s tests_python -p "test_*.py"
```
