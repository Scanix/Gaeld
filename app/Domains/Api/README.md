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
