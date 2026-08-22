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

## 4. Create and publish a draft

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/journal-entries" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-draft-0001" \
  --data '{
    "date": "2026-08-21",
    "reference": "QUICKSTART-DRAFT-0001",
    "description": "Community API draft",
    "status": "draft",
    "lines": [
      {"account_code": "1020", "debit": "25.00", "credit": "0.00"},
      {"account_code": "3000", "debit": "0.00", "credit": "25.00"}
    ]
  }'
```

Store the returned `data.id` as `DRAFT_ID`, then publish it:

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/journal-entries/$DRAFT_ID/post" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-draft-post-0001"
```

Expected result: `200` and `posted` status. Repeating this exact request must
return the same entry without posting it twice.

## 5. Reverse a posted entry

Using `JOURNAL_ENTRY_ID` from step 3:

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/journal-entries/$JOURNAL_ENTRY_ID/reverse" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-reverse-0001" \
  --data '{"description":"Community API correction"}'
```

Expected result: `200`, a new draft with a `REV-` reference, and the original
posted entry unchanged.

## 6. Create an integration contact

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/contacts" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-contact-0001" \
  --data '{
    "type": "organization",
    "name": "Quickstart Customer AG",
    "email": "customer@example.test"
  }'
```

Expected result: `201` and a public contact UUID suitable for the invoice
`customer_id` field.

## 7. Verify the entry

```bash
curl --fail-with-body "$GAELD_URL/api/v1/journal-entries?reference=QUICKSTART-2026-0001" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN"
```

Expected result: exactly one matching entry belonging to the token's
organization.

## 8. Replay the same request

Repeat the exact command from step 3 with the same `Idempotency-Key` and body.
The response must represent the original operation and must not create a second
journal entry. Reusing the key with a changed amount must return `409`.

## 9. Create and finalize an invoice

Store the contact UUID returned by step 6 as `CUSTOMER_ID`:

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/invoices" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-invoice-0001" \
  --data "{
    \"customer_id\": \"$CUSTOMER_ID\",
    \"issue_date\": \"2026-08-21\",
    \"due_date\": \"2026-09-20\",
    \"currency\": \"CHF\",
    \"lines\": [{\"description\": \"Integration service\", \"quantity\": 1, \"unit_price\": 100}]
  }"
```

Store `data.id` as `INVOICE_ID`, then finalize it:

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/invoices/$INVOICE_ID/finalize" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-invoice-finalize-0001"
```

Expected result: `200` with `data.journal_entry.status` equal to `posted`.

## 10. Create, approve, and post an expense

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/expenses" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-expense-0001" \
  --data '{
    "category": "Office supplies",
    "description": "Integration expense",
    "amount": "50.00",
    "date": "2026-08-21",
    "vendor": "Supplier AG",
    "currency": "CHF"
  }'
```

Store `data.id` as `EXPENSE_ID`, then run approval and posting:

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/expenses/$EXPENSE_ID/approve" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-expense-approve-0001"

curl --fail-with-body -X POST "$GAELD_URL/api/v1/expenses/$EXPENSE_ID/post-to-ledger" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-expense-post-0001" \
  --data '{"expense_account_code":"6500","bank_account_code":"1020"}'
```

Expected result: the final response contains a posted `data.journal_entry`.

## 11. Verify reporting

Open the organization's trial balance or call the supported reporting/export
workflow. The debit and credit effect must appear once and remain balanced.

## 12. Import CAMT.053

```bash
curl --fail-with-body -X POST "$GAELD_URL/api/v1/bank-accounts/{bank-account-uuid}/imports/camt053" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer $GAELD_TOKEN" \
  -H "Idempotency-Key: quickstart-camt-0001" \
  -F "camt_file=@tests/fixtures/camt053_sample.xml"
```

Expected result: `201` with an import UUID and a transaction count. Repeating
the same statement with a different key must not duplicate bank transactions.

## 13. Security checks

- Call the endpoint without `Authorization`; expect `401`.
- Use a token without `accounting.create`; expect `403` for creation.
- Try a public UUID from another organization; expect `403` or `404` without
  leaking whether the record exists.
- Disable the installation API flag and retry; expect `403`.

## 14. Automated verification

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
