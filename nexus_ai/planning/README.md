# Nexus planning

The planner converts an objective into declarative steps. It does not execute
tools, grant permissions, or bypass Laravel authorization. Each proposed tool
must be checked against the permissions and descriptors supplied by Laravel.
Missing data, unavailable tools, and missing permissions make a step
non-feasible rather than silently authorizing it.
