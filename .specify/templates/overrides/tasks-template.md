---

description: "Gäld task list template for feature implementation"

---

# Tasks: [FEATURE NAME]

**Input**: Design documents from `/specs/[###-feature-name]/`

**Prerequisites**: `spec.md` and `plan.md`; include `research.md`,
`data-model.md`, `contracts/`, and `quickstart.md` when the plan requires them.

## Task Format

Use:

```text
[ID] [P?] [US#] Description with an exact repository path
```

- `[P]` means the task can run in parallel without conflicting file changes.
- `[US#]` maps the task to a user story in `spec.md`.
- Every implementation task names the real file or directory it changes.
- Tests are mandatory for new behavior in Gäld; do not omit them because the
  generic Spec Kit template treats tests as optional.

## Repository Conventions

Use the existing structure:

```text
app/Domains/[Domain]/
resources/js/Pages/[Domain]/
resources/js/Components/
resources/js/lib/
routes/
database/migrations/
tests/Feature/
tests/Unit/
```

Use existing Actions, Services, DTOs, Form Requests, Policies, Query objects,
translations, and components before creating new abstractions.

## Phase 1: Shared Setup

Include only work genuinely shared by multiple user stories:

- [ ] T001 [P] [US#] [Concrete setup task with exact path]

## Phase 2: Foundational Contract and Security

Complete prerequisites before story implementation:

- [ ] T002 [US#] Add or update authorization and organization-scope tests in `tests/Feature/...`
- [ ] T003 [US#] Add or update validation and contract tests in `tests/Feature/...`
- [ ] T004 [US#] Add migration or shared domain contract in the exact planned path, if required

Do not create a foundation phase for abstractions that no story needs.

## Phase 3: User Story 1 - [Title] (Priority: P1)

**Goal**: [What this story delivers]

**Independent Test**: [How this story proves value without depending on later stories]

### Tests First

- [ ] T005 [P] [US1] Add the failing feature/integration test for the acceptance scenarios in `tests/Feature/...`
- [ ] T006 [P] [US1] Add unit coverage for the domain rule in `tests/Unit/...`, when a unit boundary exists

### Implementation

- [ ] T007 [US1] Update the Form Request and authorization boundary in `app/Domains/...`
- [ ] T008 [US1] Implement the domain operation in the existing Action or Service at `app/Domains/...`
- [ ] T009 [US1] Update the named route/controller at `routes/...` and `app/Domains/...`
- [ ] T010 [US1] Update the Inertia page/component and states at `resources/js/...`
- [ ] T011 [US1] Add translations, documentation, or release notes at the exact planned paths when required

**Checkpoint**: Run the focused Sail tests and verify this story independently.

## Phase 4: User Story 2 - [Title] (Priority: P2)

Repeat the same pattern only when the specification contains a distinct,
independently testable story. Do not split one workflow into artificial phases.

- [ ] T012 [P] [US2] Add tests for acceptance, failure, authorization, and tenant-scope behavior in `tests/...`
- [ ] T013 [US2] Implement the smallest backend change in `app/...`
- [ ] T014 [US2] Implement the smallest frontend or contract change in `resources/js/...`

## Final Verification

- [ ] T015 [P] Run focused tests with `vendor/bin/sail artisan test --compact tests/...`
- [ ] T016 Run `vendor/bin/sail bin pint --dirty --format agent` for modified PHP files
- [ ] T017 Run `vendor/bin/sail phpstan analyse --memory-limit=2G` when PHP changes are present
- [ ] T018 Run the relevant frontend build/check with `vendor/bin/sail pnpm ...`
- [ ] T019 Run the `quickstart.md` validation journey and record the result
- [ ] T020 Review the spec, plan, tasks, and code together for divergence

## Dependencies and Execution Order

Document only real dependencies. User stories may proceed independently after
their required contract and security tests exist; do not invent a shared
framework phase to justify parallelism.

## Implementation Strategy

Deliver the smallest independently useful story first. Stop at each checkpoint,
run focused verification, and use `/speckit-converge` after implementation to
identify remaining gaps before adding new scope.