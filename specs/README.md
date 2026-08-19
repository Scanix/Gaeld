# Gäld Specifications

This directory contains flow-forward specifications for new or substantially
changed product behavior. It is intentionally not a retroactive rewrite of the
existing application documentation.

The reserved `000-product-baseline/` directory is the exception. It contains
the current-state audit and architecture evidence used to choose future
features; it is not an implementation specification.

Each feature directory is created by Spec Kit and normally contains some or all
of these artifacts:

- `spec.md` — user-facing intent, requirements, and acceptance scenarios
- `plan.md` — implementation approach and affected Laravel/domain surfaces
- `tasks.md` — ordered, reviewable implementation tasks
- `research.md`, `data-model.md`, `contracts/`, and `quickstart.md` when needed

Use the project-local Copilot commands in this order for substantial work:

```text
/speckit-specify
/speckit-clarify
/speckit-plan
/speckit-checklist
/speckit-tasks
/speckit-analyze
/speckit-implement
/speckit-converge
```

Existing domain READMEs, `CONTRIBUTING.md`, release runbooks, and architecture
notes remain the source of historical and operational context. New specs should
link to those documents rather than duplicate them.