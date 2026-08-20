# Implementation Plan: [FEATURE]

**Branch**: `[###-feature-name]` | **Date**: [DATE] | **Spec**: [link]

**Input**: Feature specification from `/specs/[###-feature-name]/spec.md`

## Summary

[Summarize the user outcome and the smallest technical approach that satisfies
the approved specification. Do not add unrequested capabilities.]

## Technical Context

**Language/Runtime**: PHP 8.4, Laravel 13

**Frontend**: Inertia.js v3, Vue 3, Vite, Tailwind CSS

**Storage/Infrastructure**: PostgreSQL, Redis, Horizon, filesystem storage as
used by the existing feature

**Testing**: PHPUnit through `vendor/bin/sail artisan test`; relevant frontend
build or component checks through `vendor/bin/sail pnpm`

**Project Type**: Existing Laravel web application with domain-driven modules,
Inertia pages, CE/EE feature flags, and organization tenancy

**Performance and Scale**: [State only measurable goals relevant to this
feature. Otherwise write `No new measurable target; preserve existing behavior.`]

**Constraints**: [State authorization, organization scope, accounting
invariants, localization, accessibility, compatibility, migration, and rollout
constraints that apply.]

## Existing Codebase Impact

### Domain Ownership

- **Owning domain**: [Accounting / Invoicing / Expenses / Banking / Reporting / other]
- **Invariants preserved**: [List the concrete invariants]
- **Existing services/actions to reuse**: [List real classes or `None`]
- **Existing documentation and specs consulted**: [List paths]

### Backend Surfaces

```text
app/Domains/[Domain]/Controllers/       # request orchestration only
app/Domains/[Domain]/Actions/           # business operations with side effects
app/Domains/[Domain]/Services/           # reusable domain logic
app/Domains/[Domain]/Requests/           # validation and request authorization
app/Domains/[Domain]/Policies/           # model authorization
routes/                                  # named web or API route
database/migrations/                     # only when schema changes are needed
```

Remove unused paths from the delivered plan and replace placeholders with
actual files.

### Frontend Surfaces

```text
resources/js/Pages/[Domain]/              # page composition and user journey
resources/js/Components/                  # shared UI only when genuinely reused
resources/js/lib/                         # existing frontend utilities/contracts
lang/                                     # user-facing translations when needed
```

## Constitution Check

Before implementation, confirm:

- [ ] The owning domain and accounting invariants are explicit.
- [ ] Existing Actions, Services, DTOs, Requests, Policies, and components were checked first.
- [ ] Organization scope is enforced in model queries, raw queries, and validation rules as appropriate.
- [ ] Authentication, authorization, validation, and failure behavior are specified.
- [ ] The design does not add a package, project, or abstraction without a concrete reason.
- [ ] Tests cover the acceptance scenarios, including relevant failure and tenant-isolation paths.
- [ ] Migration, rollback, compatibility, and release impact are addressed when applicable.

## Data and Contract Changes

**Data model**: [Describe changed entities, columns, relationships, or `None`]

**HTTP/Inertia/API contract**: [Describe routes, request fields, response props,
status codes, download formats, or `None`]

**Frontend states**: [Describe loading, empty, validation, error, forbidden,
archived, and success states that apply]

## Test Strategy

List tests by behavior, not only by class:

- **Feature/integration**: [Exact `tests/Feature/...` paths]
- **Unit/domain**: [Exact `tests/Unit/...` paths]
- **Frontend/build**: [Exact check or path, or `None`]
- **Manual/browser**: [Exact journey and environment, or `None`]

All new behavior must be verifiable through the repository's Sail workflow.

## Project Structure

```text
specs/[###-feature-name]/
├── spec.md
├── checklists/requirements.md
├── plan.md
├── research.md                 # only when research is needed
├── data-model.md               # only when data changes need detail
├── contracts/                  # only when external/API contracts need detail
├── quickstart.md               # validation steps for the feature
└── tasks.md
```

## Rollout and Operations

- **Migration/backfill**: [Plan or `None`]
- **Feature flag**: [Existing flag, new flag with justification, or `None`]
- **Queue/scheduler/storage impact**: [Plan or `None`]
- **Monitoring and rollback**: [Plan or `None`]
- **Documentation/changelog**: [Required updates or `None`]

## Complexity Tracking

Record only justified deviations from the constitution:

| Deviation | Why it is needed | Simpler alternative rejected because |
|---|---|---|
| [None or concrete deviation] | [Reason] | [Evidence] |