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

In the official Community Edition, API access is enabled by default for the
installation. An administrator may disable the complete API surface with the
documented installation-level feature flag; token abilities and organization
membership remain mandatory even when the flag is enabled.

For a self-hosted installation, the local administrator can bootstrap the
first organization token without using the web UI:

```bash
vendor/bin/sail artisan gaeld:token <organization-uuid> \
  --name="Integration token" \
  --abilities=banking.view \
  --abilities=banking.create \
  --abilities=banking.import \
  --expires-in-days=365
```

The command selects the first organization owner unless `--user` is supplied.
It prints the plain-text token once. It is deliberately a local/admin command,
not an unauthenticated API bootstrap endpoint.

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
- `banking.view`, `banking.create`, `banking.import`
- `contacts.view`, `contacts.create`, `contacts.edit`

`GET /api/v1/meta/abilities` is the source of truth for abilities accepted by
that installation.

Journal-entry operations use the following existing abilities:

- `accounting.view` for account and journal-entry reads;
- `accounting.create` for creating drafts or posted entries;
- `accounting.edit` for posting drafts and reversing posted entries;
- `accounting.delete` for deleting unarchived drafts.

## Journal Entries

### List

```http
GET /api/v1/journal-entries?status=posted&from=2026-01-01&to=2026-12-31&page=1&per_page=20
```

Filters are optional and include `status` (`draft` or `posted`), inclusive date
range (`from`, `to`), and `reference`. The response is paginated with a
maximum page size of 100. An empty result returns `data: []` with pagination
metadata rather than a missing or null data property.

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
side. Monetary values are decimal strings with no more than two fractional
digits and the existing ledger maximum. Debit and credit totals must be equal
after server-side decimal validation.

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

### Contacts and Customers

Contacts are available through both `/contacts` and the backward-compatible
`/customers` alias:

- `GET /api/v1/contacts` and `GET /api/v1/customers` list organization contacts
  with `search`, `type`, `page`, and `per_page` filters;
- `GET`, `POST`, `PUT`, and `DELETE` are available for the corresponding
  public UUID resource, subject to contact abilities and organization scope.

The contact UUID returned by these endpoints is the value used as
`customer_id` when creating an invoice.

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

Business-document and CAMT.053 mutations are atomic at the domain boundary: a
failed request does not leave a partial document, ledger posting, or bank import
that a retry could duplicate.

CAMT.053 import is a separate banking contract. It validates the supported
file format, scopes the target bank account to the token organization, and
uses the file's transaction identifiers to make retries safe.

```http
POST /api/v1/bank-accounts/{bankAccount}/imports/camt053
Idempotency-Key: postfinance-2026-08-21
Content-Type: multipart/form-data
```

The multipart field is `camt_file`. The response contains the import UUID,
bank-account UUID, detected format, statement identifier, and number of newly
created transactions. Re-importing the same statement with a different key
returns an import with zero new transactions.

### Bank account creation

```http
POST /api/v1/bank-accounts
Idempotency-Key: bank-account-postfinance-2026
```

```json
{
  "name": "PostFinance",
  "iban": "CH9300762011623852957",
  "qr_iban": "CH4431999123000889012",
  "currency": "CHF",
  "account_code": "1020"
}
```

The request requires `banking.create` and an `account_code` referring to an
active account in the token organization. The response uses the same
`BankAccountResource` as the read endpoints and includes the public bank
account and linked ledger-account identifiers.

### Invoice PDF

```http
GET /api/v1/invoices/{invoice}/pdf
```

The request requires `invoicing.view` and returns `application/pdf` bytes with
an attachment filename. Missing payment-account data returns `qr_iban_required`;
QR-bill validation failures return `qr_bill_invalid` in the normal JSON error
envelope.

## Idempotency

`Idempotency-Key` is optional but recommended for every mutating request. When
it is omitted, an endpoint may use a documented natural or accounting reference
as its fallback. Existing business-resource mutations without a natural
reference use a deterministic fingerprint of the complete request payload or
uploaded file as that safe fallback. Journal-entry mutations without a key or
reference remain rejected because they must expose an explicit accounting
identity.

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
  "code": "journal_entry_unbalanced",
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
- `409`: duplicate reference (`duplicate_reference`), idempotency payload
  conflict (`idempotency_conflict`), or invalid state transition caused by a
  concurrent request (`concurrent_transition`);
- `422`: malformed or invalid business payload;
- `429`: API rate limit reached, with the existing retry headers.

## Compatibility

The prefix remains `/api/v1`. Existing routes and response fields remain
backward-compatible. New fields are additive. A breaking change requires a
new API version and an explicit changelog entry.

## Terminology

- **Source metadata**: the stable origin label for an API-created entry plus
  the related external reference or Gäld business-document relationship when
  one exists.
- **External reference**: a caller-supplied identifier from the source system
  that remains stable across retries and is not an internal Gäld database ID.
- **Natural/accounting reference**: the journal or document reference already
  used by Gäld to enforce organization-scoped uniqueness when no HTTP
  idempotency key is supplied.
- **Safe retry**: repeating the same mutation with the same organization,
  route, key or fallback reference, and payload without creating a second
  accounting or import effect.
