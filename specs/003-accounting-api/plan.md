# Implementation Plan: Community Accounting API

**Branch**: `003-accounting-api` | **Date**: 2026-08-21 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `specs/003-accounting-api/spec.md`

## Summary

Expose the existing accounting domain through the versioned REST API so a
Community Edition installation can be integrated with external bookkeeping
and logbook applications. The first usable slice adds journal-entry reads and
writes, draft/post/reverse lifecycle operations, account-code resolution,
organization-scoped authorization, and retry protection. Existing invoice,
expense, banking, and CAMT.053 workflows remain domain-owned and are aligned
with the same contract incrementally.

The implementation will add a thin API boundary around existing DTOs,
policies, events, and `LedgerService`. Controllers will validate and map
public request data; they will not write `JournalEntry` or `TransactionLine`
models directly. A reusable API idempotency service will store completed
mutations and replay the original response when a client retries safely.

## Technical Context

**Language/Runtime**: PHP 8.4, Laravel 13

**Frontend**: No frontend changes. The feature is a JSON REST API consumed by
external applications; existing Inertia journal-entry workflows remain intact.

**Storage/Infrastructure**: PostgreSQL for journal and idempotency records;
Redis for the existing API rate limiter and ledger/report cache tags. No new
package, queue, scheduler, or filesystem dependency is required.

**Testing**: PHPUnit through `vendor/bin/sail artisan test --compact`, Pint
through `vendor/bin/sail bin pint --dirty --format agent`, and PHPStan through
`vendor/bin/sail bin phpstan analyse --memory-limit=2G`. The frontend build is
not required unless shared frontend files are touched.

**Project Type**: Existing Laravel domain-driven application with Sanctum
tokens, organization tenancy, policy authorization, CE/EE feature flags, and
an existing API v1 surface.

**Performance and Scale**: Preserve the existing default API limit of 60
requests per minute. A single journal-entry read or mutation should target a
sub-500 ms p95 response on the supported self-hosted baseline, excluding
client network time and database contention. List endpoints must remain
paginated and must not load an unbounded organization ledger into memory.

**Constraints**: All ledger mutations go through `LedgerService`; account
codes resolve only inside the token organization; archived and posted entries
remain immutable; tokens and abilities are enforced server-side; existing v1
clients and web workflows remain compatible; the CE release enables API access
by default while retaining the existing flag as an installation-level kill
switch; errors and documentation remain JSON and localization-independent at
the protocol layer.

## Existing Codebase Impact

### Domain Ownership

- **Owning domain**: The API domain owns transport, token abilities,
	idempotency, resources, and JSON errors. Accounting owns journal-entry
	lifecycle and ledger invariants. Invoicing, Expenses, and Banking retain
	ownership of their document and import workflows.
- **Invariants preserved**: Balanced double-entry lines, positive amount on
	exactly one side, organization-scoped account existence, duplicate reference
	rules, closed fiscal-year protection, draft/posted/reversal transitions,
	immutable archived records, cache invalidation, and existing audit/events.
- **Existing services/actions/DTOs to reuse**: `LedgerService`,
	`LedgerQueryService`, `JournalEntryData`, `JournalLineData`,
	`JournalEntryPolicy`, `AccountPolicy`, `InvoiceApiController`,
	`ExpenseApiController`, invoice and expense Actions, `CurrentOrganization`,
	`EnsureApiOrganization`, `TokenPermissionMap`, `FeatureFlag`, and existing
	accounting events.
- **Existing documentation and specs consulted**: `AGENTS.md`,
	`.github/copilot-instructions.md`, `.specify/memory/constitution.md`,
	`routes/api.php`, `contract/api-contract.json`,
	`config/features.php`, `app/Console/Commands/GaeldReleaseCommand.php`,
	`specs/README.md`, and the public API documentation.

### Backend Surfaces

```text
routes/api.php                                      # journal-entry routes and middleware
app/Domains/Api/Controllers/JournalEntryApiController.php
app/Domains/Api/Requests/StoreJournalEntryApiRequest.php
app/Domains/Api/Requests/JournalEntryActionApiRequest.php
app/Domains/Api/Resources/JournalEntryResource.php
app/Domains/Api/Services/ApiIdempotencyService.php
app/Domains/Api/Models/ApiIdempotencyKey.php
app/Http/Middleware/Api/TokenPermissionMap.php     # policy-to-token ability map
app/Domains/Accounting/DTOs/JournalEntryData.php   # internal account-id DTO
app/Domains/Accounting/Services/LedgerService.php  # sole ledger write boundary
app/Domains/Accounting/Services/LedgerQueryService.php
app/Domains/Accounting/Policies/JournalEntryPolicy.php
app/Domains/Accounting/Models/Account.php
app/Domains/Accounting/Models/JournalEntry.php
app/Domains/Api/Controllers/InvoiceApiController.php
app/Domains/Api/Controllers/ExpenseApiController.php
app/Domains/Api/Controllers/BankAccountApiController.php
app/Domains/Api/Controllers/AccountApiController.php
app/Support/ConfigFeatureResolver.php
config/features.php
app/Console/Commands/GaeldReleaseCommand.php
database/migrations/2026_08_21_000000_create_api_idempotency_keys_table.php
contract/api-contract.json
```

The API controller is orchestration only. Account-code resolution belongs in a
small API mapper/service that queries `Account` with the current organization
scope and returns internal IDs to the existing DTO. Idempotency reservation,
payload hashing, transaction boundaries, and response replay belong in the API
service, not in each controller method.

### Frontend Surfaces

None. No new browser page or shared Vue component is required. Existing web
routes are not part of the external API contract and must not be repurposed as
integration endpoints.

## Constitution Check

Before implementation, confirm:

- [x] The owning domain and accounting invariants are explicit.
- [x] Existing Actions, Services, DTOs, Requests, Policies, and components
	were checked first.
- [x] Organization scope is enforced in model queries, route resolution, and
	validation rules as appropriate.
- [x] Authentication, authorization, validation, and failure behavior are
	specified.
- [x] The design does not add a package, project, or abstraction without a
	concrete reason.
- [x] Tests cover the acceptance scenarios, including failure and
	tenant-isolation paths.
- [x] Migration, rollback, compatibility, and release impact are addressed.

## Data and Contract Changes

**Data model**:

- Add an organization-scoped `api_idempotency_keys` table with a UUID primary
	key, organization ID, request key, HTTP method, route name, payload hash,
	response status, response JSON, and timestamps/expiry. Add a unique index on
	organization ID plus request key, and indexes for expiry cleanup and route
	lookup. Store no bearer token or sensitive request headers.
- Do not duplicate journal lines or introduce a second ledger model. Reuse the
	existing `journal_entries.type` for the API source marker when the source is
	needed in a response; add a journal metadata column only if the existing
	type/event data cannot represent the required source without ambiguity.
- Existing `accounts.organization_id + code` uniqueness is the canonical
	account-code lookup contract. Public UUIDs remain response identifiers.
- No backfill is required. The idempotency table starts empty and old journal,
	invoice, expense, and bank records remain unchanged.

**HTTP/API contract**:

- Keep the `/api/v1` prefix, Sanctum bearer authentication, organization
	resolution, rate limiting, and JSON error envelope.
- Add `GET /api/v1/journal-entries`,
	`GET /api/v1/journal-entries/{journalEntry}`,
	`POST /api/v1/journal-entries`,
	`POST /api/v1/journal-entries/{journalEntry}/post`,
	`POST /api/v1/journal-entries/{journalEntry}/reverse`, and `DELETE
	/api/v1/journal-entries/{journalEntry}` for drafts only.
- Use `date`, `reference`, `description`, `status`, and `lines` in create
	requests. Each line uses `account_code`, `debit`, `credit`, and an optional
	description. The server maps codes to internal account IDs.
- Require an explicit `status` value of `draft` or `posted` in create requests.
- Accept an optional `Idempotency-Key` header. When absent, use an applicable
	endpoint-specific external or accounting reference as the documented
	fallback; creation endpoints without either a safe key or natural reference
	must return a clear validation response rather than imply retry safety.
- Return public UUIDs, account code/name/type, status, totals, source, dates,
	references, and links to related business documents where present. Never
	require or expose internal integer IDs in the contract.
- Use `201` for a newly created entry, `200` for reads and lifecycle actions,
	`204` for deleting a draft, `401` for authentication failure, `403` for
	denied abilities or organization access, `404` for absent public resources,
	`409` for duplicate/conflicting idempotency or reference operations, `422`
	for validation/domain errors, and `429` for rate limiting.
- Update `meta/abilities` to derive from the supported permission map rather
	than maintain a stale hardcoded list. Reuse existing `accounting.*`,
	`invoicing.*`, `expenses.*`, `banking.*`, and `contacts.*` permissions.
- Keep existing invoice and expense routes backward-compatible while exposing
	their generated journal-entry relationship in resources. Treat CAMT.053 as a
	separate bank-import contract and do not make it a generic journal-entry
	endpoint.

**Frontend states**: None for the new API. Existing web flows must retain their
loading, validation, forbidden, archived, and success behavior because the
shared ledger services are being extended, not replaced.

## Test Strategy

List tests by behavior, not only by class:

- **Feature/integration**:
	`tests/Feature/Api/JournalEntryApiTest.php` for account lookup, create draft,
	create posted, list/show filters, post, reverse, delete draft, and JSON
	statuses; `tests/Feature/Api/ApiIdempotencyTest.php` for first request,
	replay, payload conflict, fallback reference, and failed transaction retry;
	`tests/Feature/Api/InvoiceApiUpdateTest.php` and new invoice/expense API
	regression cases for linked journal-entry results; existing accounting HTTP
	flow tests for unchanged web behavior.
- **Security/authorization**:
	`tests/Security/Api/JournalEntryApiSecurityTest.php` for missing/expired/
	revoked tokens, token abilities, personal membership, organization-token
	behavior, cross-organization account and entry access, archived state, and
	rate-limit responses; extend `FeatureFlagEnforcementTest.php` for the CE
	default and explicit kill-switch.
- **Unit/domain**:
	`tests/Unit/Api/AccountCodeResolverTest.php`,
	`tests/Unit/Api/ApiIdempotencyServiceTest.php`, and focused
	`tests/Unit/LedgerServiceTest.php` additions for source metadata and existing
	balance/closed-year invariants.
- **Frontend/build**: None unless shared frontend code changes.
- **Manual/browser**: Use the quickstart to create a CE token, retrieve account
	codes, create and replay a posted entry, post a draft, reverse it, and verify
	the trial balance; run against a Sail installation with no SaaS subscription.

All PHP tests and static checks run through Sail. Tests must cover both happy
paths and failure paths before implementation tasks are considered complete.

## Project Structure

```text
specs/003-accounting-api/
├── spec.md
├── checklists/requirements.md
├── checklists/api.md
├── plan.md
├── contracts/api-v1.md
├── quickstart.md
└── tasks.md
```

No `research.md` or `data-model.md` is needed: the existing code and the
contract/validation artifacts contain the required decisions at this scope.

## Rollout and Operations

- **Migration/backfill**: Add the idempotency table with a reversible migration;
	no data backfill. A rollback must be performed only after disabling clients
	that depend on replay records because dropping the table removes retry
	history. Do not modify existing migrations.
- **Feature flag**: Keep `features.api_access` as an installation-level kill
	switch but change the CE default and release command to enabled. Update
	`.env.example`, `.env.production.example` only where the documented default
	is inconsistent. SaaS remains enabled.
- **Queue/scheduler/storage impact**: No queue or scheduler work. Add a small
	cleanup command or scheduled cleanup only if existing operations conventions
	require expiry deletion; otherwise purge expired rows opportunistically on
	reservation with bounded work.
- **Monitoring and rollback**: Log API route, organization, token type, result
	class, latency, and idempotency outcome without token values or payload
	secrets. Reuse existing journal events/webhooks for created, posted, and
	reversed entries. Disable the API flag to stop new requests while preserving
	existing ledger data; rollback the migration only after client coordination.
- **Documentation/changelog**: Update `contract/api-contract.json`, the OpenAPI
	source/public API documentation, `README.md`, `INSTALL.md`, and
	`CHANGELOG.md`. Add the API quickstart and migration/compatibility notes to
	the release checklist when the CE default changes.

## Complexity Tracking

Record only justified deviations from the constitution:

| Deviation | Why it is needed | Simpler alternative rejected because |
|---|---|---|
| Add one reusable API idempotency service and table | Multiple mutating resources need consistent safe retry semantics and response replay | Per-controller duplicate checks would drift and cannot safely coordinate concurrent requests |
| Add a separate public API resource layer for journal entries | External clients need stable account-code and UUID representations without leaking internal IDs | Returning Eloquent models would couple the public contract to database structure |