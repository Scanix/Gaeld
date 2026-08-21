# Tasks: Community Accounting API

**Input**: Design documents from `specs/003-accounting-api/`

**Prerequisites**: `spec.md`, `plan.md`, `contracts/api-v1.md`, and
`quickstart.md`.

## Task Format

Every task follows:

```text
- [ ] T### [P] [US1] Description with an exact repository path
```

`[P]` means the task can run in parallel with other tasks in the same phase
without conflicting file changes. Story labels map to the prioritized user
stories in `spec.md`. Tests are mandatory for new behavior in Gäld.

## Repository Conventions

Use the existing Laravel domain structure, Sail commands, policies, Form
Requests, Resources, DTOs, Actions, Services, and PHPUnit conventions. Do not
write journal models directly from an API controller; all ledger mutations must
reach `app/Domains/Accounting/Services/LedgerService.php`.

## Phase 1: Shared Setup

- [ ] T001 [P] Add the API v1 journal-entry contract and examples to `contract/api-contract.json`, preserving all existing route definitions.
- [ ] T002 [P] Add the external contract review checklist for released routes and response shapes to `specs/003-accounting-api/contracts/api-v1.md`.
- [ ] T003 [P] Add the executable external-client validation flow to `specs/003-accounting-api/quickstart.md`.

## Phase 2: Foundational Contract and Security

**Goal**: Establish CE availability, idempotency storage, shared API behavior,
and authorization before implementing any user story endpoint.

- [ ] T004 Add the organization-scoped idempotency migration with unique request-key, payload-hash, response, and expiry indexes in `database/migrations/2026_08_21_000000_create_api_idempotency_keys_table.php`.
- [ ] T005 Add the idempotency Eloquent model, casts, organization relationship, and guarded fields in `app/Domains/Api/Models/ApiIdempotencyKey.php`.
- [ ] T006 Implement reservation, payload conflict detection, response persistence, replay, and bounded expiry handling in `app/Domains/Api/Services/ApiIdempotencyService.php`.
- [ ] T007 Add shared idempotency integration at the API mutation boundary in `app/Http/Middleware/Api/HandleIdempotency.php`, including transaction-safe concurrent reservation behavior.
- [ ] T008 Extend policy-to-token ability mapping and derive the supported ability list in `app/Http/Middleware/Api/TokenPermissionMap.php` and `app/Domains/Api/Controllers/ApiTokenController.php`.
- [ ] T009 Make API access enabled by default in the Community Edition while retaining the installation kill switch in `config/features.php`, `.env.example`, `.env.production.example`, and `app/Console/Commands/GaeldReleaseCommand.php`.
- [ ] T010 [P] Add foundational authorization, feature-flag, and organization-isolation tests for the CE API contract in `tests/Security/Api/JournalEntryApiSecurityTest.php` and `tests/Security/Authorization/FeatureFlagEnforcementTest.php`.
- [ ] T011 [P] Add idempotency service and concurrent reservation tests in `tests/Unit/Api/ApiIdempotencyServiceTest.php`.
- [ ] T012 [P] Add contract assertions for status codes, error envelopes, and supported abilities in `tests/Feature/Api/ApiContractTest.php`.

## Phase 3: User Story 1 - Automate General Journal Entries (Priority: P1)

**Goal**: Let an authorized external client create a balanced draft or posted
journal entry using organization account codes and receive a stable resource.

**Independent Test**: With one CE organization and token, read accounts, create
one draft and one posted entry, then verify returned lines and rejection of an
unbalanced or unknown-account payload.

### Tests First

- [ ] T013 [P] [US1] Add feature tests for account lookup, draft creation, immediate posting, balanced-line validation, amount precision, minimum line count, unknown/inactive account rejection, and resource shape in `tests/Feature/Api/JournalEntryApiTest.php`.
- [ ] T014 [P] [US1] Add unit tests for organization-scoped account-code resolution and rejection of duplicate/ambiguous codes in `tests/Unit/Api/AccountCodeResolverTest.php`.

### Implementation

- [ ] T015 [US1] Implement account-code lookup constrained by `CurrentOrganization` and active-account rules in `app/Domains/Api/Services/AccountCodeResolver.php`.
- [ ] T016 [US1] Add API request validation and authorization for date, reference, status, lines, decimal amounts, and `account_code` in `app/Domains/Api/Requests/StoreJournalEntryApiRequest.php`.
- [ ] T017 [US1] Map validated account codes to `JournalEntryData` and `JournalLineData` without changing the internal ledger DTO contract in `app/Domains/Api/Services/JournalEntryApiMapper.php`.
- [ ] T018 [US1] Add the public journal-entry resource including UUIDs, account codes, totals, status, source, and lines in `app/Domains/Api/Resources/JournalEntryResource.php`.
- [ ] T019 [US1] Implement create and read orchestration using policies, `LedgerService`, `LedgerQueryService`, organization-scoped queries, and the API source marker in `app/Domains/Api/Controllers/JournalEntryApiController.php`.
- [ ] T020 [US1] Register versioned journal-entry routes under the existing Sanctum/API middleware group in `routes/api.php`.
- [ ] T021 [US1] Add journal-entry API events/webhook payload coverage without exposing token secrets in `app/Domains/Api/Enums/WebhookEvent.php` and `app/Domains/Api/Services/WebhookService.php`.
- [ ] T022 [US1] Run the focused US1 PHPUnit tests and record the independent checkpoint in `specs/003-accounting-api/quickstart.md`.

## Phase 4: User Story 2 - Manage and Inspect the Accounting Lifecycle (Priority: P1)

**Goal**: Let an external client list and inspect entries, post drafts, reverse
posted entries, and enforce immutable posted/archived states.

**Independent Test**: Create an entry through the API, list and retrieve it,
post a draft, reverse a posted entry, retry each lifecycle operation, and verify
that the original posted entry is not edited or duplicated.

### Tests First

- [ ] T023 [P] [US2] Add lifecycle feature tests for pagination, date/status/reference filters, post, reverse, draft deletion, duplicate transitions, and immutable states in `tests/Feature/Api/JournalEntryLifecycleApiTest.php`.
- [ ] T024 [P] [US2] Add lifecycle authorization and cross-organization tests for view, post, reverse, edit, and delete abilities in `tests/Security/Api/JournalEntryApiSecurityTest.php`.

### Implementation

- [ ] T025 [US2] Add lifecycle request validation for optional reversal description and idempotency fallback fields in `app/Domains/Api/Requests/JournalEntryActionApiRequest.php`.
- [ ] T026 [US2] Implement paginated journal-entry queries and filters with eager-loaded lines/accounts and bounded page sizes in `app/Domains/Api/Queries/JournalEntryApiQuery.php`.
- [ ] T027 [US2] Implement post, reverse, and draft-delete actions through `LedgerService` and `JournalEntryPolicy` in `app/Domains/Api/Controllers/JournalEntryApiController.php`.
- [ ] T028 [US2] Extend `TokenPermissionMap` and policy coverage for journal-entry `viewAny`, `view`, `create`, `post`, `reverse`, `update`, and `delete` operations in `app/Http/Middleware/Api/TokenPermissionMap.php` and `app/Domains/Accounting/Policies/JournalEntryPolicy.php`.
- [ ] T029 [US2] Add lifecycle webhook/event assertions for posted and reversed entries in `app/Domains/Api/Enums/WebhookEvent.php` and `tests/Feature/Api/JournalEntryLifecycleApiTest.php`.
- [ ] T030 [US2] Run the focused US2 PHPUnit and security tests and update the lifecycle checkpoint in `specs/003-accounting-api/quickstart.md`.

## Phase 5: User Story 3 - Integrate Business Documents and Bank Data (Priority: P2)

**Goal**: Complete the supported CE integration surface for business documents,
linked accounting results, contacts, and safe CAMT.053 imports without using
session routes or direct database access.

**Independent Test**: Create and post one invoice or expense through the API,
verify its linked journal entry, import a valid CAMT.053 file, retry the import,
and verify no duplicate bank transactions are produced.

### Tests First

- [ ] T031 [P] [US3] Add invoice and expense workflow tests for linked journal-entry UUID/status, atomic partial-failure handling, and retry behavior in `tests/Feature/Api/BusinessDocumentApiTest.php`.
- [ ] T032 [P] [US3] Add CAMT.053 success, malformed-file, unknown-bank-account, atomic import failure, duplicate-transaction, and retry tests in `tests/Feature/Api/BankImportApiTest.php`.
- [ ] T033 [P] [US3] Add cross-organization and ability tests for contacts, invoices, expenses, bank accounts, and imports in `tests/Security/Api/BusinessApiSecurityTest.php`.

### Implementation

- [ ] T034 [US3] Add or align contact/customer API resources and routes with organization-scoped policies in `app/Domains/Api/Controllers/ContactApiController.php` and `routes/api.php`.
- [ ] T035 [US3] Expose linked journal-entry UUID/status and source metadata in `app/Domains/Api/Resources/InvoiceResource.php` and `app/Domains/Api/Resources/ExpenseResource.php` without changing domain posting rules.
- [ ] T036 [US3] Add a documented CAMT.053 API request, controller, resource/result, and organization-scoped import orchestration in `app/Domains/Api/Requests/ImportCamt053ApiRequest.php`, `app/Domains/Api/Controllers/BankImportApiController.php`, and `routes/api.php`.
- [ ] T037 [US3] Connect invoice, expense, contact, and bank-import mutations to the shared idempotency boundary in `app/Domains/Api/Services/ApiIdempotencyService.php` and the affected API controllers.
- [ ] T038 [US3] Update the API contract and quickstart examples for business documents and CAMT.053 in `specs/003-accounting-api/contracts/api-v1.md` and `specs/003-accounting-api/quickstart.md`.
- [ ] T039 [US3] Run the focused US3 PHPUnit and security tests and record the independent checkpoint in `specs/003-accounting-api/quickstart.md`.

## Phase 6: Polish and Cross-Cutting Verification

- [ ] T040 [P] Update the machine-readable API contract and public documentation for released routes, abilities, payloads, errors, idempotency, and CE availability in `contract/api-contract.json`, `README.md`, and `INSTALL.md`.
- [ ] T041 [P] Add the Community Edition API availability, migration, compatibility, and rollback notes to `CHANGELOG.md` and `RELEASE.md`.
- [ ] T042 [P] Review API logging and audit payloads to ensure organization, route, result, latency, and idempotency outcome are present while bearer tokens and secrets are absent in `app/Http/Middleware/LogOrgTokenActivity.php`, `app/Domains/Api/Services/WebhookService.php`, and related tests.
- [ ] T043 Run the focused API, accounting, and security tests through `vendor/bin/sail artisan test --compact` for the paths listed in `specs/003-accounting-api/plan.md`.
- [ ] T044 Run `vendor/bin/sail bin pint --dirty --format agent` and inspect the resulting PHP diff for the modified files in `app/`, `routes/`, `database/migrations/`, and `tests/`.
- [ ] T045 Run `vendor/bin/sail bin phpstan analyse --memory-limit=2G` and resolve new diagnostics in the API/accounting slices.
- [ ] T046 Execute every command in `specs/003-accounting-api/quickstart.md` against a CE Sail installation and record expected status codes and ledger results.
- [ ] T047 Run the full PHPUnit suite with `vendor/bin/sail artisan test --compact` and confirm existing web invoice, expense, banking, reporting, and journal workflows remain green.
- [ ] T048 Run `/speckit-converge` after implementation and reconcile any remaining differences among `spec.md`, `plan.md`, `tasks.md`, and the changed code in `specs/003-accounting-api/`.

## Dependencies and Execution Order

- T001-T003 establish the external contract artifacts and can run in parallel.
- T004-T012 are foundational; T010-T012 may run in parallel after their test
  fixtures are understood, but story implementation starts only after the
  idempotency and permission boundaries are agreed.
- US1 (T013-T022) is the first independently useful slice and is the MVP.
- US2 depends on US1's resource, routes, and permission mapping, then can be
  completed independently of US3.
- US3 depends on the shared idempotency boundary and existing business API
  resources, but does not change the journal-entry lifecycle contract.
- T040-T048 follow the story checkpoints; T040-T042 can run in parallel before
  the final verification commands.

## Parallel Execution Examples

### After foundational security boundaries

```text
T013 + T014  (US1 feature and resolver tests)
T023 + T024  (US2 lifecycle and security tests)
T031 + T032 + T033  (US3 business and import tests)
```

### During final polish

```text
T040 + T041 + T042  (documentation, release, and observability review)
```

Parallel tasks must not edit the same file concurrently; where a listed task
shares `routes/api.php`, `TokenPermissionMap.php`, or a controller, execute the
tasks serially despite the story-level grouping.

## Implementation Strategy

1. Deliver US1 as the MVP: read accounts, create draft/posted journal entries,
   validate ledger invariants, and prove CE token isolation.
2. Stop at the US1 checkpoint and run focused Sail tests before adding lifecycle
   operations.
3. Add US2 lifecycle controls and idempotent retries without changing the
   internal accounting service contract.
4. Add US3 business-document relationships and CAMT.053 only after the generic
   journal contract is stable.
5. Complete release documentation, static analysis, full tests, quickstart
   validation, and `/speckit-converge` before implementation is considered
   complete.
