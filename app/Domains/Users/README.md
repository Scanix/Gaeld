# Users Domain

User accounts, authentication, profile management, and GDPR data export.

## Scope

- **Users**: authentication credentials, profile, and preferences
- **Registration**: account creation with email verification
- **WebAuthn**: passkey/FIDO2 passwordless authentication
- **Data Export**: GDPR-compliant personal data export
- **Dashboard Layout**: per-user widget arrangement preferences

## Models

- **User** — Authenticatable user with profile, preferences, and organization memberships

## Services

- **UserService** — Profile updates, password management, and account operations
- **DataExportService** — GDPR personal data export generation

## Integration

- Users belong to one or more Organizations via pivot table
- Sanctum tokens managed by the Api domain
- WebAuthn via `laragear/webauthn` package
