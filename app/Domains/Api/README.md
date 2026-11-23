# Api Domain

External API layer: personal access tokens, webhook delivery, and API-specific resources.

## Scope

- **Personal Access Tokens**: Sanctum-based API authentication with scoped permissions
- **Webhooks**: event-driven HTTP callbacks to external systems
- **Webhook Calls**: delivery log with retry tracking

## Models

- **PersonalAccessToken** — Extended Sanctum token with custom scopes
- **Webhook** — Registered callback URL with event subscriptions
- **WebhookCall** — Individual delivery attempt with payload and response status

## Integration

- Wraps domain resources with API-specific transformations
- Uses Laravel Sanctum for token authentication
- Webhook events are dispatched from other domains (Invoicing, Banking, etc.)

## External references

Contacts, their `customers` alias, and invoices accept `external_source` (up to 100 characters) and `external_id` (up to 255 characters). Send both non-empty strings when assigning or changing a reference. Existing records can omit both. Matching is exact and case-sensitive. A reference is unique within an organization and resource type, including soft-deleted records.

Use either field as a query parameter on the collection. For example, `GET /api/v1/contacts?external_source=shopify&external_id=customer-12345` returns matching contacts visible to the organization.

Send the usual creation payload with both reference fields to `POST /api/v1/contacts/upsert`, `POST /api/v1/customers/upsert`, or `POST /api/v1/invoices/upsert`. A new resource returns HTTP 201. An existing contact or editable draft invoice returns HTTP 200 after applying the payload. Both contact URLs refer to the same record. A soft-deleted reference returns HTTP 409 with `external_reference_conflict`. An organization can reuse an identifier from another organization.

An explicit `Idempotency-Key` on an upsert replays the original response for the same payload for its original credential after checking permission for the original operation. Reusing that key with different input returns HTTP 409. An ordinary collection POST keeps its existing creation and idempotency behavior.

New webhook calls are stored with their event and delivery IDs when the business transaction commits. If the queue is temporarily unavailable, the scheduler retries the same calls every minute. Operators can run `php artisan webhooks:dispatch-pending` to process due calls. Receivers can use the delivery ID to deduplicate a request if acknowledgment was interrupted.
