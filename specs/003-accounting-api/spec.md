# Feature Specification: Community Accounting API

**Feature Branch**: `003-accounting-api`

**Created**: 2026-08-21

**Status**: Draft

**Input**: Provide a complete, secure accounting API in the Community Edition
so external applications can read accounts and create, publish, reverse, and
inspect journal entries while preserving organization isolation, ledger
integrity, idempotent retries, and existing invoice, expense, and CAMT.053
workflows.

## Clarifications

### Session 2026-08-21

- Q: Dans la Community Edition, l'API doit-elle être activée par défaut ou
  seulement après une activation explicite par l'administrateur de
  l'installation ? -> A: Activée par défaut dans toute installation CE; les
  tokens et permissions restent obligatoires.
- Q: Les clients doivent-ils fournir une clé d'idempotence pour chaque
  requête API qui modifie des données ? -> A: La clé est optionnelle mais
  recommandée; une référence externe ou métier sert de fallback lorsqu'elle
  existe.
- Q: Quel identifiant les requêtes doivent-elles utiliser pour référencer un
  compte comptable ? -> A: Le code comptable est l'identifiant d'intégration
  principal; les UUID publics restent disponibles dans les réponses.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Automate general journal entries (Priority: P1)

As a bookkeeper using an external application, I want to send balanced journal
entries to Gäld so that recurring or imported bookkeeping work does not have to
be entered manually.

**Why this priority**: Direct journal-entry creation is the missing capability
that prevents the logbook integration described in the feature request.

**Independent Test**: Using a valid organization-scoped API token, create one
draft and one immediately posted entry with account codes, then verify the
entries and their accounting effect through the API and the Gäld reports.

**Acceptance Scenarios**:

1. **Given** an active organization and a token with accounting write access,
   **when** the client submits a balanced entry in draft mode, **then** Gäld
   stores it as a draft and returns its public identifier, reference, lines,
   and status.
2. **Given** an active organization and a token with accounting write access,
   **when** the client submits a balanced entry in posted mode, **then** Gäld
   posts it immediately and the entry is included in the organization's
   accounting balances.
3. **Given** an entry whose debit and credit totals differ, **when** the client
   submits it, **then** Gäld rejects it with a structured validation error and
   creates no entry or ledger lines.
4. **Given** an entry containing an unknown or inactive account, **when** the
   client submits it, **then** Gäld rejects it and identifies the invalid
   account without exposing internal database identifiers.

---

### User Story 2 - Safely manage and inspect the accounting lifecycle (Priority: P1)

As an organization owner or integration operator, I want to read the chart of
accounts and journal entries, publish drafts, and reverse posted entries so that
the external system can reconcile its state with Gäld without direct database
access.

**Why this priority**: An integration is only dependable when it can verify
what was accepted and handle corrections through an auditable workflow.

**Independent Test**: Create an entry through the API, retrieve it using its
public identifier and list filters, publish a draft, reverse a posted entry,
and verify that the original remains immutable while the reversal is traceable.

**Acceptance Scenarios**:

1. **Given** a token with accounting read access, **when** the client requests
   accounts or journal entries, **then** only records from the token's
   organization are returned with stable public identifiers and pagination.
2. **Given** a draft entry, **when** a token with posting permission requests
   publication, **then** Gäld validates the fiscal-year and balance rules,
   posts the entry once, and returns the posted representation.
3. **Given** a posted entry, **when** a token with reversal permission requests
   a reversal, **then** Gäld creates a separately identifiable contra-entry and
   leaves the original posted entry unchanged.
4. **Given** a posted or archived entry, **when** a client attempts to edit or
   delete it, **then** Gäld rejects the operation and reports the applicable
   state restriction.

---

### User Story 3 - Integrate business documents and bank data (Priority: P2)

As a small organization's bookkeeper, I want one documented API surface for
contacts, invoices, expenses, payments, accounts, and bank imports so that an
external application can use the appropriate business workflow instead of
reconstructing accounting records through internal routes.

**Why this priority**: Not every transaction should be represented as a
manual journal entry. Invoices, expenses, and CAMT.053 data need their own
domain workflows and must remain connected to the ledger.

**Independent Test**: In a Community Edition installation, use the documented
API to create a business document, complete its accounting workflow, retrieve
the generated journal-entry reference, and import a CAMT.053 file without
requiring a web session or direct database access.

**Acceptance Scenarios**:

1. **Given** a valid customer and invoice payload, **when** the client creates
   and finalizes an invoice, **then** Gäld creates the invoice's accounting
   entry through its normal business rules and exposes the relationship.
2. **Given** a valid expense payload, **when** the client approves and posts
   the expense, **then** Gäld creates the expense's accounting entry and
   exposes its resulting status and relationship.
3. **Given** a valid CAMT.053 import for an organization bank account, **when**
   the client submits the import, **then** Gäld validates the file, prevents
   duplicate transactions on retry, and reports the import result.
4. **Given** an installation using the Community Edition, **when** an owner
  creates an API token with the required abilities, **then** the supported API
  contract is available without requiring a SaaS subscription.

### Edge Cases

- A request is repeated after a timeout with the same idempotency key.
- The same idempotency key is reused with a different payload.
- A request has a duplicate accounting reference but no idempotency key.
- A token is expired, revoked, lacks the required ability, or belongs to a
  user no longer attached to the organization.
- A token attempts to access an object from another organization.
- An entry date falls in a closed fiscal year or outside the organization's
  permitted accounting period.
- A draft is posted concurrently by two requests.
- A posted entry is reversed more than once.
- A request contains zero, negative, excessive-precision, or non-decimal
  amounts, or lines with both debit and credit set.
- An import contains malformed CAMT.053 data, duplicate transaction IDs, or a
  transaction for an unknown bank account.
- An invoice or expense workflow partially fails and is retried.
- A paginated list is empty or a requested public identifier no longer exists.
- The API is disabled, rate-limited, or temporarily unavailable.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: The system MUST expose a versioned JSON API for supported
  accounting integrations in the Community Edition.
- **FR-002**: The system MUST authenticate API requests with organization-scoped
  bearer tokens and MUST reject missing, expired, revoked, or unassociated
  tokens.
- **FR-003**: The system MUST enforce token abilities and server-side policies
  for every read and write operation.
- **FR-004**: The system MUST enforce organization isolation for every API
  request, including route parameters, account references, documents, bank
  accounts, and imports.
- **FR-005**: The system MUST expose organization account reference data needed
  to construct an entry without requiring internal database IDs, and journal
  entry requests MUST use the organization's account code as the primary
  integration identifier.
- **FR-006**: The system MUST allow an authorized client to create a journal
  entry with a date, optional reference, optional description, account-code
  based lines, and an explicit draft or posted status.
- **FR-007**: The system MUST validate that every journal entry is balanced and
  that each line has a valid account and a positive amount on exactly one side.
- **FR-008**: The system MUST reject journal entries dated in a closed fiscal
  year and MUST preserve the existing accounting-period rules.
- **FR-009**: All journal mutations MUST use the existing accounting domain
  rules and MUST produce the same ledger and reporting effects as the web
  workflow.
- **FR-010**: The system MUST allow an authorized client to retrieve one
  journal entry with its lines, accounts, reference, status, source metadata,
  and public identifier.
- **FR-011**: The system MUST allow authorized clients to list journal entries
  with pagination and filters for date range, reference, and status.
- **FR-012**: The system MUST allow an authorized client to post a draft once
  and MUST reject repeated or concurrent publication of the same draft.
- **FR-013**: The system MUST allow an authorized client to reverse a posted
  entry by creating a traceable contra-entry without editing the original.
- **FR-014**: The system MUST prevent editing or deleting posted or archived
  entries and MUST apply the existing permissions to draft mutations.
- **FR-015**: The system MUST support an optional idempotency key for every
  mutating API operation and MUST return the original result for a safe retry
  of the same request; when no key is provided, the system MUST use an
  applicable external or accounting reference as the documented fallback, and
  a mutation without either a key or a safe fallback MUST be rejected before
  any side effect.
- **FR-016**: The system MUST reject reuse of an idempotency key with a
  different request payload and MUST prevent duplicate accounting effects when
  either the key or the documented fallback identifies a repeated request.
- **FR-017**: The system MUST preserve accounting-reference uniqueness within an
  organization and MUST return a structured conflict when it is violated.
- **FR-018**: The system MUST return stable JSON error structures for
  authentication, authorization, not-found, validation, conflict, rate-limit,
  and domain-rule failures.
- **FR-019**: The system MUST expose existing invoice and expense workflows in
  the Community Edition without requiring an internal web session.
- **FR-020**: Invoice and expense API responses MUST expose the resulting
  accounting-entry relationship when a document has been posted.
- **FR-021**: The system MUST provide a documented CAMT.053 import workflow for
  supported Community Edition bank-import scenarios and MUST make retries safe.
- **FR-022**: The system MUST emit auditable events for API-created, posted, and
  reversed journal entries and for failed authorization or cross-organization
  attempts, without recording bearer-token secrets.
- **FR-023**: The system MUST apply a documented rate limit and return enough
  response metadata for a client to retry after a limit is reached.
- **FR-024**: The API contract MUST use public, stable identifiers and MUST NOT
  expose internal database implementation details as integration requirements.
- **FR-025**: The API documentation MUST provide examples for authentication,
  account lookup, draft creation, immediate posting, publication, reversal,
  idempotent retry, business-document posting, and CAMT.053 import.

### Out of Scope

- Direct database writes or reads by external applications.
- Calling session-based web or Inertia routes as an integration contract.
- Automatic bank connections or bank-provider synchronization.
- Editing a posted journal entry in place.
- Replacing Gäld's existing invoice, expense, VAT, fiscal-year, or archival
  business rules with client-side calculations.

### Key Entities

- **API Token**: An expiring or revocable credential scoped to one organization
  and a set of read/write abilities.
- **Account**: An organization's chart-of-accounts item identified to clients by
  its account code for integration and by a stable public identifier in API
  responses.
- **Journal Entry**: A dated, balanced accounting record containing a reference,
  description, status, source, and transaction lines.
- **Transaction Line**: One debit or credit amount linked to an organization
  account and optional line description.
- **Idempotency Record**: The organization-scoped record of a mutating request,
  its payload fingerprint, result, and replay status.
- **Invoice or Expense**: A business document whose domain workflow may create
  a linked journal entry.
- **Bank Import**: A CAMT.053 import and its resulting bank transactions,
  duplicate-detection state, and import summary.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: A bookkeeper can create and verify a balanced journal entry from
  an external client in no more than three API requests after obtaining a token.
- **SC-002**: 100% of API-created journal entries either pass the same balance,
  account, organization, and fiscal-year rules as the web workflow or are
  rejected without persisted ledger mutations.
- **SC-003**: Replaying any successful mutating request with the same
  idempotency key, or with the same documented fallback reference when no key
  is supplied, produces no additional journal entry, transaction, invoice,
  expense, or bank transaction.
- **SC-004**: Automated security tests demonstrate that a token cannot read or
  mutate data belonging to another organization, even when given a valid public
  identifier from that organization.
- **SC-005**: The focused API test suite covers all documented journal-entry
  operations, all documented error classes, and every edge case listed above
  before release.
- **SC-006**: A clean Community Edition installation can execute the documented
  API quickstart without a SaaS subscription or session-based browser login.
- **SC-007**: The API documentation contains a runnable example for each
  primary integration journey and matches the released routes and response
  shapes.
- **SC-008**: Existing web invoice, expense, banking, reporting, and journal
  workflows continue to pass their focused regression tests after API changes.

## Assumptions

- Existing bearer-token authentication and organization resolution remain the
  foundation of the API contract.
- Existing policies, permissions, DTOs, actions, and accounting services are
  reused unless the plan documents a necessary extension.
- Monetary values are exchanged as decimal values with explicit precision; the
  server remains authoritative for rounding and VAT calculations.
- API access is included and enabled by default in the Community Edition; token
  creation, abilities, organization membership, and policies remain mandatory
  access controls.
- The first implementation can deliver journal entries as the MVP while
  preserving the existing invoice, expense, and bank-import endpoints for
  subsequent contract harmonization.
- External systems provide their own retry handling, secure token storage, and
  mapping between their source records and Gäld references.
- CAMT.053 support remains limited to the bank-import formats already supported
  by Gäld; automatic bank-provider synchronization is a separate feature.
- The release must preserve existing API clients and web workflows unless a
  versioned breaking change is explicitly approved.
