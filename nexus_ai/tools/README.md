# Python to Laravel tool bridge

`NexusLaravelToolBridge` consumes the definitions returned by Laravel
`NexusToolRegistry`. It validates tool names, permissions, protected arguments,
and read/write classification, then creates a non-executable proposal.

Python never calls a Laravel tool, database, permission manager, or security
boundary. Laravel must pass the proposal through `NexusToolRegistry`,
`NexusPermissionManager`, and `NexusSecurityBoundary`, then return the
structured result to `accept_result()`.
