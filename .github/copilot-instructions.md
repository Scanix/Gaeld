# Gaeld Project Instructions

## Authority

Use the smallest applicable source of truth:

1. `AGENTS.md` defines Laravel, Sail, testing, and repository conventions.
2. The active feature spec under `specs/` defines intended behavior for work in progress.
3. Code and tests define current runtime behavior; report conflicts instead of guessing.
4. Domain `README.md` files provide local context only.
5. Skills under `.github/skills/` and `.agents/skills/` are on-demand workflows, not parallel architecture rules.

Do not create a new guidance document when an existing source can be updated.
Do not duplicate rules from `AGENTS.md` or specialized skills here.

## Architecture

- Preserve the existing Laravel domain structure under `app/Domains`.
- Controllers orchestrate requests; Actions and Services own business operations with side effects or invariants.
- All ledger writes go through `LedgerService`; reporting remains read-only.
- Organization isolation is mandatory. Use the existing `BelongsToOrganization`, policies, Form Requests, and explicit scoping for raw queries and jobs.
- Use Laravel, Inertia, Vue, and existing components directly before adding abstractions or dependencies.
- Frontend calculations improve feedback only. Server-side accounting values, validation, and authorization are authoritative.
- Do not refactor for aesthetics. Consolidate only when duplication causes behavioral drift, security risk, or meaningful testability problems.

## Frontend and Backend Contract

- Named Laravel routes are the navigation contract.
- Inertia page props must have one owner and explicit loading, empty, error, forbidden, and archived states where applicable.
- Never use a zero or empty frontend fallback to hide a missing financial prop. Log and test contract mismatches, then fix the owning backend/frontend boundary.
- Reuse shared Vue components and translation helpers before creating new ones.

## Change Workflow

- Small, obvious fixes may proceed directly with focused tests.
- Substantial behavior changes use the active Spec Kit flow:
  `/speckit-specify`, `/speckit-clarify`, `/speckit-plan`, `/speckit-tasks`,
  `/speckit-analyze`, `/speckit-implement`, `/speckit-converge`.
- Keep one active feature spec per bounded change. Do not create product-wide audit documents or speculative architecture plans.
- Before implementation, ensure the spec, plan, and tasks agree. After implementation, run `/speckit-converge` and review the diff.
- Keep commits atomic and focused. Do not include unrelated worktree changes.

## Verification

Run project commands through Sail. For PHP changes, run the focused PHPUnit
tests, Pint, and PHPStan. For frontend changes, run the focused checks and
`vendor/bin/sail pnpm run build` when the bundle is affected. Never claim a
workflow is verified when Docker, the test, or the required runtime check was
not available.

For Stripe or SaaS billing work, use the existing Stripe skills under
`.agents/skills/` and keep CE/EE boundaries explicit. Do not copy Stripe rules
into this file.
