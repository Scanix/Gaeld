# Gäld Specifications

This directory contains flow-forward specifications for new or substantially
changed product behavior. It is intentionally not a retroactive rewrite of the
existing application documentation.

Each feature directory is created by Spec Kit and normally contains some or all
of these artifacts:

- `spec.md` — user-facing intent, requirements, and acceptance scenarios
- `plan.md` — implementation approach and affected Laravel/domain surfaces
- `tasks.md` — ordered, reviewable implementation tasks
- `research.md`, `data-model.md`, `contracts/`, and `quickstart.md` only when
	the plan cannot remain precise without them

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

Existing domain READMEs, `AGENTS.md`, `CONTRIBUTING.md`, release runbooks, and
the codebase remain the source of historical and operational context. New specs
should link to those sources rather than duplicate them.

For a normal Gäld feature, start with exactly `spec.md`, `plan.md`, and
`tasks.md`. Keep research, data models, contracts, and validation notes inside
`plan.md` unless they are large enough to require independent review.