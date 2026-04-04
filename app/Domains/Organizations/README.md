# Organizations Domain

Multi-tenant organization management: setup, invitations, switching, and onboarding checklist.

## Scope

- **Organizations**: tenant isolation boundary with legal name, address, VAT number
- **Invitations**: email-based team member onboarding with role assignment
- **Setup Wizard**: guided initial configuration (chart of accounts, fiscal year, currency)
- **Onboarding Checklist**: tracks completion of key getting-started steps
- **Organization Switching**: multi-org user context management

## Models

- **Organization** — Tenant record with legal details and configuration
- **OrganizationInvitation** — Pending team invitation with email, role, and expiry

## Services

- **OrganizationService** — CRUD and configuration management
- **OrganizationSetupService** — Guided setup wizard logic
- **CurrentOrganization** — Request-scoped tenant context resolver
- **OrganizationSwitcher** — Multi-org session management
- **InvitationService** — Invitation lifecycle (create, accept, expire)
- **ChecklistService** — Onboarding progress tracking across domains

## Integration

- Every other domain uses `organization_id` for tenant scoping
- `CurrentOrganization` is injected across all controllers for tenant resolution
