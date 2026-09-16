# Nexus memory and context

This layer stores conversation references and session context only. It does not
represent authoritative DevControl state: current projects, bugs, tasks,
incidents, and updates must still come from Laravel. Memory cannot grant
permissions or execute tools.

Session records expire by default after 30 minutes. Persistent records can be
exported to JSON, but no database or external storage is connected in this
phase.
