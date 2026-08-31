# Feature Specification: SaaS Admin Console

**Feature Branch**: `002-saas-admin-console`

**Created**: 2026-08-20

**Status**: Ready for Implementation

**Input**: User request: improve the EE SaaS administration with a useful, real administration interface and a clean software architecture.

## User Scenarios & Testing

### User Story 1 - Understand the SaaS estate and find an organization (Priority: P1)

As a SaaS operator, I want a concise overview of adoption, subscriptions,
revenue, health, and operational alerts so that I can decide what needs
attention without reading several unrelated tables.

**Why this priority**: The current dashboard mixes many concerns and still
requires manual interpretation. A reliable overview and fast organization
search are the minimum support workflow.

**Independent Test**: Given organizations with active, trialing, past-due,
dormant, suspended, and never-used states, the operator can open the overview,
understand the main counts, and find one organization using search and filters
without loading every organization into one page.

**Acceptance Scenarios**:

1. **Given** organizations with different subscription and activity states,
   **when** the operator opens the SaaS admin area, **then** the overview shows
   separate activation, subscription, revenue, health, and operational signal
   groups with explicit periods and data freshness.
2. **Given** more organizations than fit on one page, **when** the operator
   searches by organization name, organization identifier, or owner email,
   **then** matching results are returned with pagination and the selected
   filters remain visible.
3. **Given** an organization with no activity or no subscription,
   **when** the operator opens the filtered organization list, **then** the
   row clearly distinguishes unknown data from a measured zero.
4. **Given** an empty result or a failed aggregate, **when** the overview is
   displayed, **then** it shows an explicit empty or error state and does not
   replace missing financial data with zero.

---

### User Story 2 - Diagnose and manage an organization (Priority: P1)

As a SaaS operator, I want a complete organization detail view and safe
lifecycle actions so that I can support a customer, resolve access problems,
and manage a plan without switching between unrelated tools.

**Why this priority**: Support work is the primary operational value of the
admin area. The current detail page lacks reliable last-login data, useful
activation context, and a structured action history.

**Independent Test**: Given one organization with members, activity,
subscription history, and a suspension state, the operator can inspect the
organization, perform an allowed lifecycle action, see the result, and find
that action in the audit history. A non-admin cannot perform the same action.

**Acceptance Scenarios**:

1. **Given** an organization, **when** the operator opens its detail view,
   **then** the page shows identity, owner and members, activation signals,
   usage by domain, current subscription, subscription history, suspension
   state, known recent activity, and administrative history.
2. **Given** an organization with an active, trialing, past-due, canceled,
   paused, or admin-granted plan, **when** the operator views billing details,
   **then** the local status, plan limits, Stripe linkage state, and dates are
   displayed without exposing payment secrets.
3. **Given** an organization that can be suspended, reactivated, assigned a
   plan, revoked, or deleted, **when** the operator confirms the action,
   **then** the server validates the target and records the action with actor,
   target, reason, and relevant before/after values.
4. **Given** an organization belonging to another tenant context, **when** a
   request attempts to access it through an admin route, **then** the request
   is resolved only through the SaaS admin authorization boundary and cannot
   fall back to ordinary organization permissions.
5. **Given** a lifecycle action fails or conflicts with the current state,
   **when** the operator submits it, **then** no partial mutation is presented
   as successful and the interface explains the current state and next step.

---

### User Story 3 - Monitor billing, product health, and operations (Priority: P2)

As a SaaS operator, I want dedicated billing, health, and operations views so
that I can distinguish commercial issues from product adoption issues and
platform incidents.

**Why this priority**: Revenue, activation, webhook synchronization, and
registration controls require different decisions. Combining them in one
scrolling page makes important signals easy to miss.

**Independent Test**: Given subscriptions, plans, webhook receipts, usage
signals, and a registration kill-switch state, the operator can inspect each
category separately, filter it, and identify an actionable issue.

**Acceptance Scenarios**:

1. **Given** active subscriptions, trials, past-due subscriptions, canceled
   subscriptions, and subscriptions without Stripe linkage, **when** the
   operator opens Billing, **then** each state has a count, list, and clear
   next action.
2. **Given** a local subscription and a linked Stripe customer,
   **when** the operator explicitly requests a Stripe diagnostic, **then** the
   interface reports customer, subscription, payment-method presence, recent
   invoices, last local synchronization, and detected differences or a clear
   external-service error.
3. **Given** organizations with activity in the last 7, 30, and 90 days,
   **when** the operator opens Health, **then** activation and dormancy cohorts,
   quota signals, and recent activity can be compared by period.
4. **Given** failed or unprocessed webhook receipts, **when** the operator
   opens Operations, **then** recent synchronization failures are visible with
   event type, age, organization linkage when available, and a diagnostic
   status that does not expose payload secrets.
5. **Given** a global system message or registration kill-switch state,
   **when** the operator opens Operations, **then** the current state, last
   change, scope, and reversal action are explicit.

---

### User Story 4 - Provide support, communication, and controlled exports (Priority: P2)

As a SaaS operator, I want controlled support sessions, targeted communication,
and useful exports so that I can assist customers and report on the business
without losing accountability or exposing more data than necessary.

**Why this priority**: Support sometimes needs the customer's own view, but
impersonation, email, and customer-data exports are sensitive operations that
must be designed as first-class workflows rather than ad hoc buttons.

**Independent Test**: Given an eligible organization member, a selected
recipient, and a filtered organization set, the operator can start and stop a
support session, send a targeted message, request an export, and later inspect
the complete audit trail. Expired or unauthorized operations are rejected.

**Acceptance Scenarios**:

1. **Given** a selected member and a required support reason, **when** the
   operator starts support access, **then** a session limited to 15 minutes is
   created, the application displays a persistent support banner, and the
   operator can stop it immediately.
2. **Given** an impersonated support session, **when** the user attempts to
   access SaaS admin, 2FA/security settings, billing administration, or another
   support session, **then** access is denied and the original admin identity
   remains attached to audit records.
3. **Given** an in-scope organization or explicitly selected recipients,
   **when** the operator prepares an email, **then** the recipients, subject,
   body, locale, and reason are shown for confirmation; all members are never
   selected implicitly.
4. **Given** a filtered organization list, **when** the operator requests an
   operational CSV or financial report, **then** the export preserves the
   filters and period used, reports progress or queued status, and provides a
   time-limited download when ready.
5. **Given** a customer-data export request, **when** the operator confirms
   the request with a reason, **then** the export is queued, access is
   time-limited, the included data categories are explicit, and the request is
   audited separately from an operational report.
6. **Given** an expired support session, signed download, or failed queued job,
   **when** it is reused, **then** the operation is rejected or marked failed
   without exposing data or claiming success.

### Edge Cases

- The organization list contains 5,000 organizations and many share similar names.
- Search matches an owner email but the owner belongs to several organizations.
- An organization has no members, no subscription, no activity, or only deleted records.
- A local subscription has no Stripe identifiers because it was granted by an admin.
- Stripe is unavailable, rate-limited, returns incomplete data, or disagrees with the local record.
- A webhook event has failed processing, has no organization metadata, or is retried concurrently.
- A plan is assigned while another request changes or cancels the subscription.
- A suspension, reactivation, or deletion request is repeated after the state has changed.
- An organization is suspended while a support session is active.
- An impersonation session expires while a form is open or a mutation is submitted.
- The original admin logs out or loses the session during impersonation.
- An email recipient has no valid email, has a different locale, or is selected twice.
- An export contains no matching organizations or includes an organization deleted after filtering.
- A large export fails after partial file creation or its signed URL expires.
- A user attempts to access a support, export, or diagnostic URL without the SaaS admin confirmation.
- The EE plugin is disabled or SaaS mode is off; core CE flows must remain unchanged.

## Requirements

### Functional Requirements

- **FR-001**: The system MUST restrict SaaS administration to the configured SaaS admin identity, a verified account with 2FA enabled, and the existing temporary confirmation step.
- **FR-002**: The system MUST present separate overview, organizations, billing/plans, health/activity, and operations workflows instead of one undifferentiated dashboard.
- **FR-003**: The organization list MUST support server-side search, filtering, sorting, pagination, and explicit empty, error, suspended, and deleted states.
- **FR-004**: Organization search MUST support organization name, organization identifier, and owner email without exposing organizations to ordinary tenant users.
- **FR-005**: The overview MUST show separate, period-labeled metrics for organization count, subscription status, trials, past due, MRR, activation, and health; every metric MUST identify its period or freshness.
- **FR-006**: Activation MUST distinguish organization creation, first known login, first business activity, and activity retention at 7 and 30 days when the source data exists.
- **FR-007**: Health classification MUST distinguish suspended, unknown, never used, dormant, quiet, and healthy states in that precedence order using UTC and a rolling 30 x 24-hour window. `suspended` applies whenever the organization is suspended; `unknown` applies when the activity source is unavailable; `never_used` applies when no known activity exists; `dormant` applies when the last known activity is more than 30 x 24 hours old; `quiet` applies when the last known activity is exactly 30 x 24 hours old or more recent and there are fewer than 3 activity events in the selected 30-day period; `healthy` applies when the last known activity is exactly 30 x 24 hours old or more recent and there are at least 3 activity events.
- **FR-008**: Missing or unavailable financial and usage data MUST be represented as unknown or unavailable; the frontend MUST NOT use zero or empty fallbacks to hide a missing backend prop.
- **FR-009**: The organization detail MUST show aggregate product usage, members and roles, the best available last activity/login signal, onboarding/activation signals, local subscription history, Stripe linkage state, and SaaS admin audit entries.
- **FR-010**: Suspension, reactivation, plan grant/change, revocation, and deletion MUST be server-authorized, validated, transactionally applied where multiple records change, confirmed in the interface, and audited.
- **FR-011**: Billing views MUST separate local subscription state, plan configuration, Stripe linkage, and external diagnostic state; locally granted plans MUST remain usable without Stripe identifiers.
- **FR-012**: Stripe diagnostics MUST be explicitly requested, must use the existing billing integration, must expose only non-sensitive summaries, and must return a clear unavailable or mismatch state when Stripe cannot be consulted.
- **FR-013**: Health and activity views MUST support 7-day, 30-day, and 90-day comparisons, dormant/quiet cohorts, usage signals, and recent webhook synchronization health without loading all organization records into a single response.
- **FR-014**: Operations MUST show the current global message and registration gate state, the last change metadata, recent webhook processing failures, and safe links to infrastructure monitoring.
- **FR-015**: Every SaaS admin mutation, diagnostic request, support session, email campaign, export request, and global operation MUST record the acting admin, target, action, timestamp, reason when applicable, request correlation identifier, and minimal before/after data needed for review. Successful, rejected, and rolled-back operations MUST be distinguishable in the audit record; rejected and rolled-back attempts MUST be recorded even when no transaction commits.
- **FR-016**: Support access MUST require a selected organization member and reason, expire after 15 minutes, show a persistent banner, provide an immediate stop action, preserve the original admin identity, and deny SaaS admin, security, billing administration, and nested support access.
- **FR-017**: Targeted email MUST require explicit recipients or an explicit organization segment, show a confirmation preview, respect recipient validity and locale, avoid implicit all-member delivery, queue delivery, and record campaign results.
- **FR-018**: Operational CSV exports, financial reports, and customer-data exports MUST preserve the selected filters and period; large or sensitive exports MUST be queued, signed for a limited time, cleaned up, and audited.
- **FR-019**: The interface MUST define loading, empty, error, forbidden, expired, unavailable, and partially failed states for every asynchronous or sensitive workflow.
- **FR-020**: All organization identifiers, filters, exports, diagnostics, and mutations MUST enforce SaaS admin authorization and MUST NOT fall back to the current tenant scope or leak another organization's data.
- **FR-021**: The administration MUST support at least 5,000 organizations while keeping the initial overview and organization search usable within 3 seconds under representative data volume.
- **FR-022**: The interface MUST preserve the existing English, French, German, and Italian localization and accessibility conventions, including keyboard-accessible confirmations and a visible state for support access.
- **FR-023**: The implementation MUST remain inside the EE plugin for SaaS-only behavior and MUST leave Community Edition routes and organization workflows unchanged when SaaS is disabled.

### Key Entities

- **SaaS admin session**: A verified administrative session with temporary confirmation state and optional support-session metadata.
- **Organization tenant**: A customer workspace with members, subscription state, product usage, lifecycle state, and support history.
- **Plan and subscription**: Commercial entitlement data, including local status, limits, dates, Stripe linkage, and plan configuration.
- **Usage and health signal**: Aggregate activity and quota information used to classify activation and operational health without exposing unnecessary member data.
- **Stripe diagnostic snapshot**: A non-persistent or short-lived summary of Stripe customer/subscription/invoice state and local synchronization differences.
- **SaaS admin audit event**: A durable record of actor, target, action, reason, correlation identifier, and minimal before/after context.
- **Support session**: A time-limited, target-specific session that preserves the original admin identity and cannot access privileged admin workflows.
- **Communication campaign**: A targeted email request with explicit recipients or segment, preview, locale, delivery status, and audit reference.
- **Export request**: An operational, financial, or customer-data export with filters, period, sensitivity classification, queue status, retention, and signed download metadata.
- **Webhook synchronization receipt**: A durable record of an external billing event, processing state, timestamp, and safe diagnostic metadata.

## Success Criteria

### Measurable Outcomes

- **SC-001**: With 5,000 representative organizations, one warm-up request and twenty timed repetitions, the initial overview and a filtered organization search complete within 3 seconds at p95, use no more than 30 database queries per request, and add no more than 128 MB of request memory for at least 95% of requests.
- **SC-002**: Across ten scripted organization-discovery tasks performed by reviewers using the SaaS admin console, at least nine tasks find the correct organization by name, identifier, owner email, subscription state, health state, or pagination and open its detail view without leaving the console.
- **SC-003**: 100% of successful, rejected, and rolled-back SaaS admin mutations, support sessions, targeted emails, exports, and global operations have a corresponding audit event with actor, target, action, outcome, and timestamp.
- **SC-004**: Across multi-organization security fixtures, 100% of requests made by non-admin users or expired support sessions are denied and 0 records outside the authorized target are returned.
- **SC-005**: For a local subscription with and without Stripe linkage, operators can distinguish local entitlement from external billing state in one detail workflow; Stripe outages produce an explicit diagnostic failure rather than stale-looking success.
- **SC-006**: At least 95% of valid export requests reach a queued, completed, or explicitly failed state, and completed downloads are inaccessible after their configured expiry.
- **SC-007**: The four supported locales have no missing SaaS admin translation keys, and a repeatable browser acceptance run confirms keyboard-accessible confirmation, cancellation, focus return, and persistent support-session banner behavior for all sensitive actions.
- **SC-008**: With SaaS disabled, existing CE route, authorization, and organization regression tests remain unchanged and pass without loading EE admin behavior.

## Assumptions

- The configured SaaS admin email remains the initial administrative identity model; multi-admin role management is out of scope for this feature.
- The existing 30-minute SaaS admin 2FA confirmation gate remains the entry gate. Support sessions have the stricter 15-minute duration.
- Ordinary product workflows may be used during support access, but SaaS admin, security/2FA, billing administration, and nested support workflows are prohibited.
- Targeted email defaults to the organization owner or explicitly selected members; it never targets all members implicitly.
- Local subscription data is authoritative for fast lists and entitlements. Stripe is authoritative only for explicitly requested external diagnostics and reconciliation signals.
- MRR means the sum of local active paid plan prices for the selected period; actual Stripe payments are reported separately when available.
- Large financial and customer-data exports are asynchronous. Operational CSV exports may be streamed only when their result size and sensitivity are within the configured threshold.
- Existing `DeviceSession` and authentication audit data is the source for last-known access; the interface shows unknown when no reliable signal exists.
- Existing core components, translation helpers, queue infrastructure, signed URL conventions, and Stripe client are reused.
- This feature does not add CE functionality, replace the existing Stripe webhook lifecycle, or redesign customer-facing billing.
