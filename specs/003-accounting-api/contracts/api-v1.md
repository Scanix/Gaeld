# Community Accounting API v1 Contract

This contract defines the first supported Community Edition integration surface.
The released OpenAPI document and `contract/api-contract.json` must remain
consistent with it.

## Authentication

All protected requests use:

```http
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

The token resolves exactly one organization. Personal tokens also require the
user to remain a member of that organization. Organization tokens require the
organization-management permission at creation time and are still limited to
the abilities assigned to the token.

The unauthenticated root is `GET /api/v1/`. Protected routes use the existing
Sanctum, organization-resolution, API feature, and rate-limit middleware.

## Token Abilities

The API reuses the existing permission values instead of introducing a second
permission vocabulary. Relevant values include:

- `accounting.view`
- `accounting.create`
- `accounting.edit`
- `accounting.delete`
- `invoicing.view`, `invoicing.create`, `invoicing.edit`,
  `invoicing.finalize`, `invoicing.record-payment`
- `expenses.view`, `expenses.create`, `expenses.edit`, `expenses.approve`
- `banking.view`, `banking.import`, `banking.reconcile`
- `contacts.view`, `contacts.create`, `contacts.edit`

`GET /api/v1/meta/abilities` is the source of truth for abilities accepted by
that installation.

## Journal Entries

### List

```http
GET /api/v1/journal-entries?status=posted&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

Filters are optional and include `status` (`draft` or `posted`), inclusive date
range (`from`, `to`), and `reference`. The response is paginated.

### Show

```http
GET /api/v1/journal-entries/{uuid}
```

The response includes the entry UUID, date, reference, description, status,
source, debit and credit totals, and lines. Every line includes `account_code`,
account name/type, debit, credit, and description. Internal integer database IDs
are not required by the contract.

### Create

```http
POST /api/v1/journal-entries
Idempotency-Key: logbook-entry-2026-00042
```

```json
{
  "date": "2026-08-21",
  "reference": "LOG-2026-00042",
  "description": "Monthly membership income",
  "status": "posted",
  "lines": [
    {
      "account_code": "1020",
      "debit": "500.00",
      "credit": "0.00",
      "description": "Bank"
    },
    {
      "account_code": "3000",
      "debit": "0.00",
      "credit": "500.00",
      "description": "Membership income"
    }
  ]
}
```

`status` is required and must be either `draft` or `posted`. A request must
contain at least two lines. Each line must have a positive amount on exactly one
side. Debit and credit totals must be equal after server-side decimal
validation.

The account code is the primary integration identifier and is unique within the
organization. The account UUID may be returned for reconciliation but is not
needed in the create payload.

### Post a Draft

```http
POST /api/v1/journal-entries/{uuid}/post
Idempotency-Key: post-logbook-entry-2026-00042
```

Posting validates the fiscal-year and state rules again. A successful retry
returns the already-posted representation and does not create a second entry.

### Reverse a Posted Entry

```http
POST /api/v1/journal-entries/{uuid}/reverse
Idempotency-Key: reverse-logbook-entry-2026-00042
```

This creates a separately identifiable contra-entry and leaves the original
entry unchanged. A second reversal is a conflict.

### Delete a Draft

```http
DELETE /api/v1/journal-entries/{uuid}
```

Only an unarchived draft can be deleted. Posted or archived entries are
immutable and must be reversed through the lifecycle endpoint.

## Existing Business Workflows

The existing v1 resources remain supported:

- `invoices`: CRUD, finalize, cancel, record payment, send, reminder, credit
  note;
- `expenses`: CRUD, approve, post to ledger;
- `accounts` and `bank-accounts`: read-only reference data;
- `webhooks`: CRUD and secret regeneration;
- `tokens`, `org-tokens`, and `meta` resources.

Invoice and expense responses expose their linked journal-entry UUID and status
when their domain workflow has posted to the ledger. Business workflows remain
the authoritative place for VAT, invoice, expense, payment, and rounding rules.

CAMT.053 import is a separate banking contract. It validates the supported
file format, scopes the target bank account to the token organization, and
uses the file's transaction identifiers to make retries safe.

## Idempotency

`Idempotency-Key` is optional but recommended for every mutating request. When
it is omitted, an endpoint may use a documented natural or accounting reference
as its fallback. A create operation without either a safe key or a natural
reference must fail clearly rather than promise retry safety.

For a repeated key with the same method, route, organization, and payload hash,
the server returns the original status and response body. Reusing a key with a
different payload returns `409 Conflict`. Concurrent reservations resolve to
one committed mutation; the losing request receives the committed result or a
retryable conflict according to the endpoint contract.

## Response and Error Shapes

Success responses use either a resource object or a paginated `data` array.
Validation and domain failures use:

```json
{
  "message": "The journal entry is not balanced.",
  "errors": {
    "lines": ["Debit and credit totals must be equal."]
  }
}
```

Status meanings:

- `200`: successful read or lifecycle operation;
- `201`: newly created resource;
- `204`: deleted draft;
- `401`: missing, invalid, expired, or revoked token;
- `403`: missing ability or organization access denied;
- `404`: public resource not found in the token organization;
- `409`: duplicate reference, idempotency payload conflict, or invalid state
  transition caused by a concurrent request;
- `422`: malformed or invalid business payload;
- `429`: API rate limit reached, with the existing retry headers.

## Compatibility

The prefix remains `/api/v1`. Existing routes and response fields remain
backward-compatible. New fields are additive. A breaking change requires a
new API version and an explicit changelog entry.
