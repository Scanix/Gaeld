# Community Accounting API v1 Quickstart

This guide validates the first supported integration path against a local
Community Edition installation.

## Prerequisites

- Services are running through Laravel Sail.
- The database has an organization with the standard Swiss chart of accounts.
- The operator has created an API token with `accounting.view`,
  `accounting.create`, and `accounting.edit` abilities.
- Set the base URL and token in the shell running the client:

```bash
export GAELD_URL=http://localhost:8080
export GAELD_TOKEN='paste-token-here'
```

The token must be stored securely and must not be committed or printed in CI
logs.

## 1. Check the API

```bash
curl --fail-with-body "$GAELD_URL/api/v1/" \
  -H "Accept: application/json"
```

Expected result: a JSON response identifying API version `v1` and status `ok`.

## 2. Read account reference data

```bash
curl --fail-with-body "$GAELD_URL/api/v1/accounts" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN"
```

Confirm that the response contains the organization accounts and their codes,
including the codes used by the test organization. Do not use internal integer
IDs in the integration client.

## 3. Create a posted journal entry

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/journal-entries" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-2026-0001" \
  --data '{
    "date": "2026-08-21",
    "reference": "QUICKSTART-2026-0001",
    "description": "Community API quickstart",
    "status": "posted",
    "lines": [
      {"account_code": "1020", "debit": "100.00", "credit": "0.00", "description": "Bank"},
      {"account_code": "3000", "debit": "0.00", "credit": "100.00", "description": "Income"}
    ]
  }'
```

Expected result: `201`, a public journal-entry UUID, `posted` status, and two
lines. Record the returned UUID in a local shell variable if desired.

## 4. Verify the entry

```bash
curl --fail-with-body "$GAELD_URL/api/v1/journal-entries?reference=QUICKSTART-2026-0001" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN"
```

Expected result: exactly one matching entry belonging to the token's
organization.

## 5. Replay the same request

Repeat the exact command from step 3 with the same `Idempotency-Key` and body.
The response must represent the original operation and must not create a second
journal entry. Reusing the key with a changed amount must return `409`.

## 6. Verify reporting

Open the organization's trial balance or call the supported reporting/export
workflow. The debit and credit effect must appear once and remain balanced.

## 7. Security checks

- Call the endpoint without `Authorization`; expect `401`.
- Use a token without `accounting.create`; expect `403` for creation.
- Try a public UUID from another organization; expect `403` or `404` without
  leaking whether the record exists.
- Disable the installation API flag and retry; expect `403`.

## 8. Automated verification

Run focused tests through Sail from the repository root:

```bash
vendor/bin/sail artisan test --compact tests/Feature/Api/JournalEntryApiTest.php
vendor/bin/sail artisan test --compact tests/Feature/Api/ApiIdempotencyTest.php
vendor/bin/sail artisan test --compact tests/Security/Api/JournalEntryApiSecurityTest.php
vendor/bin/sail bin pint --dirty --format agent
vendor/bin/sail bin phpstan analyse --memory-limit=2G
```

The full release gate also runs the existing feature tests and frontend build
when the bundle is affected.
