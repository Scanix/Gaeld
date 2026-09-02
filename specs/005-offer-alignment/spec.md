# Feature Specification: Gäld Offer Alignment

**Feature Branch**: `005-offer-alignment`

**Created**: 2026-09-02

**Status**: Ready for implementation planning

**Input**: Align the website, API, hosted application, and documentation with the approved Gäld offer: Community Edition free forever, Cloud Free permanent but strictly limited, Solo at CHF 15/month for up to 3 users, and Team at CHF 39/month for up to 5 users. Preserve existing subscribers and test quotas, trial fallback, and migration behavior.

## User Scenarios & Testing

### User Story 1 - Discover and choose an honest offer (Priority: P1)

As a Swiss freelancer or small business owner, I want to understand the difference between self-hosting, Cloud Free, Solo, and Team before creating an account so that I can choose an offer without hidden conversion pressure.

**Why this priority**: The offer cannot be validated commercially until the public choices and the signup flow describe the same product.

**Independent Test**: Open the localized pricing page and signup flow in each supported locale, select each available offer, and verify that the displayed price, limits, target audience, trial behavior, and call to action match the approved offer contract.

**Acceptance Scenarios**:

1. **Given** a visitor on the pricing page, **when** they compare the offers, **then** Community Edition is presented as complete and self-hostable, Cloud Free is presented as permanently free with its quotas, Solo is shown at CHF 15/month with up to 3 users, and Team is shown at CHF 39/month with up to 5 users.
2. **Given** a visitor chooses Cloud Free, **when** they create an account, **then** no payment method or paid subscription is required and the account can remain on Cloud Free.
3. **Given** a visitor chooses Solo or Team, **when** they create an account, **then** they receive a 14-day paid-plan trial without a payment method and no paid charge is created.
4. **Given** a Solo or Team trial reaches its end without an explicit paid choice, **when** the account is processed, **then** it returns to Cloud Free with its lower quotas and no data loss.
5. **Given** a visitor chooses Solo, **when** they submit signup, **then** the flow starts the same no-card trial as Team and does not silently create a paid subscription or charge.

### User Story 2 - Work within predictable plan boundaries (Priority: P1)

As a hosted Gäld customer, I want plan limits and feature access to be enforced consistently in the web application and API so that the free service remains economically sustainable and paid plans deliver what they promise.

**Why this priority**: Quotas are the boundary that makes Cloud Free viable and makes Solo/Team pricing meaningful without restricting the Community Edition.

**Independent Test**: Create one organization on each hosted plan, exercise allowed and denied invoice, OCR, storage, member, banking, automation, API, multi-currency, permissions, and payroll workflows, and verify stable user-facing errors and unchanged CE behavior.

**Acceptance Scenarios**:

1. **Given** a Cloud Free organization, **when** it has 1 organization, 1 user, 5 invoices in the current month, 5 OCR scans in the current month, or 250 MB of document storage, **then** the next operation that exceeds the applicable limit is rejected with an explicit explanation and an upgrade or export path.
2. **Given** a Cloud Free organization, **when** the user accesses core accounting, invoicing, expenses, Swiss VAT, reports, manual reconciliation, CAMT import, or export, **then** the workflow remains available.
3. **Given** a Cloud Free organization, **when** the user attempts live bank synchronization, API access, advanced automation, multi-currency, advanced permissions, or payroll processing, **then** the operation is denied without exposing data or creating a partial mutation.
4. **Given** a Solo organization, **when** the owner invites a fourth user, **then** the invitation is rejected while existing members and data remain available.
5. **Given** a Team organization, **when** the owner invites a sixth user, **then** the invitation is rejected while existing members and data remain available.
6. **Given** a SaaS organization on Solo or Team, **when** an API token calls a protected API operation, **then** only Team receives the API entitlement; Community Edition API behavior remains unchanged when SaaS is disabled.
7. **Given** a document upload would exceed the organization's storage allowance, **when** the upload is submitted, **then** it is rejected before persistence and no orphan file remains.
8. **Given** an OCR scan fails after being accepted, **when** the result is processed, **then** the scan reservation and displayed usage follow the documented counting policy and cannot be used to bypass the monthly limit.

### User Story 3 - Preserve existing subscriptions during migration (Priority: P1)

As an existing hosted customer, I want the offer migration to preserve my subscription, data, Stripe history, and agreed price until I explicitly change plans so that a pricing correction does not become an involuntary contract change.

**Why this priority**: Existing subscriptions already reference plan identifiers and external billing objects. A destructive rename or silent price mutation could create duplicate charges, break entitlements, or damage trust.

**Independent Test**: Seed legacy Free, Starter, and Business subscriptions with and without Stripe linkage, run the migration, verify their data and access, then explicitly move one paid legacy subscription to the new public plan and verify the single Stripe subscription and local history.

**Acceptance Scenarios**:

1. **Given** an existing Free subscription, **when** the migration runs, **then** it becomes the Cloud Free contract with the new Cloud Free quotas and no loss of organization data.
2. **Given** an existing Starter subscription, **when** the migration runs, **then** its original plan identifier, price, Stripe linkage, billing history, and existing access remain valid until the customer explicitly changes plan.
3. **Given** an existing Business subscription, **when** the migration runs, **then** its original plan identifier, price, Stripe linkage, billing history, and existing access remain valid until the customer explicitly changes plan.
4. **Given** a legacy paid subscription, **when** the owner explicitly changes to Solo or Team, **then** the existing Stripe subscription is updated once, local entitlements change together, and no second subscription is created.
5. **Given** an old plan is no longer offered to new customers, **when** an existing customer views Billing, **then** the interface explains its legacy status and offers a controlled transition without pretending the old plan was deleted.
6. **Given** a migration is retried, **when** it runs again, **then** no duplicate plan, subscription, Stripe customer, invoice, or migration side effect is created.

### User Story 4 - Publish one coordinated offer (Priority: P2)

As a prospective customer, I want the website, hosted application, and documentation to use the same plan names, prices, quotas, and trial explanation so that I can trust the commercial information before entering financial data.

**Why this priority**: Inconsistent public claims create support work and undermine the values of clarity and informed choice.

**Independent Test**: Compare the pricing page, signup page, Billing page, onboarding copy, public billing documentation, SaaS getting-started guide, application contract, and all four locales against the entitlement matrix, then build the website and documentation.

**Acceptance Scenarios**:

1. **Given** any supported locale, **when** a visitor opens pricing, **then** the same four offers and numeric values are displayed with translated copy.
2. **Given** a customer reads the SaaS documentation, **when** they follow the signup and trial instructions, **then** the steps describe Cloud Free, Solo, Team, the 14-day cardless paid-plan trials, and return to Cloud Free accurately.
3. **Given** a customer opens the hosted Billing page, **when** they view their current plan or available plans, **then** the public names, prices, quotas, and feature boundaries match the server-authoritative plan data.
4. **Given** the Community Edition is built or documented, **when** a user compares it with hosted plans, **then** it is not presented as a crippled trial or as requiring a SaaS subscription.
5. **Given** a public claim about backups, support, storage, data location, or retention, **when** the claim is published, **then** it has an operational owner and evidence in the readiness checklist.

## Edge Cases

- A legacy paid subscription references a plan whose public display name changes but whose Stripe price must remain unchanged.
- A legacy subscription has no Stripe identifiers because it was granted by an administrator.
- A Solo or Team trial expires while the user is logged in, has a form open, or submits a request concurrently with trial-expiry processing.
- A Solo or Team trial is converted after its trial end but before Cloud Free fallback is processed.
- A user tries to select an inactive legacy plan by submitting a crafted plan identifier.
- A plan migration is interrupted after local plan creation but before all subscriptions are checked.
- A Cloud Free account has more than 250 MB of existing documents when the new quota is introduced.
- Concurrent invoice, OCR, upload, or invitation requests race at the quota boundary.
- An upload is rejected because of storage quota after temporary file creation.
- An organization downgrades while its member count, invoice count, OCR count, or storage usage already exceeds the target plan limit.
- Stripe is unavailable during explicit conversion or legacy-plan migration.
- The API is called by a Cloud Free or Solo token while the CE feature flag is globally enabled.
- A self-hosted CE installation has no EE tables or plugin and must preserve current routes and features.
- A translation is missing a new pricing key or renders a pluralized quota incorrectly.
- A free-tier signup spike exceeds the approved operational budget without affecting existing customers.

## Requirements

### Functional Requirements

- **FR-001**: The system MUST expose exactly four customer-facing offer categories: Community Edition, Cloud Free, Solo, and Team.
- **FR-002**: Community Edition MUST remain complete, self-hostable, and usable without a SaaS subscription or hosted account.
- **FR-003**: Cloud Free MUST be permanent and MUST enforce 1 organization, 1 user, 5 invoices per calendar month, 5 OCR scans per calendar month, and 250 MB of customer document storage.
- **FR-004**: Cloud Free MUST include double-entry accounting, invoicing, expenses, Swiss VAT, reports, manual reconciliation, CAMT import, and data export.
- **FR-005**: Cloud Free MUST NOT grant live bank synchronization, external API access, advanced automation, multi-currency, advanced permissions, or payroll processing in SaaS mode.
- **FR-006**: Solo MUST cost CHF 15 per organization per month and MUST allow up to 3 named users without a per-user add-on at launch.
- **FR-007**: Team MUST cost CHF 39 per organization per month and MUST allow up to 5 named users without a per-user add-on at launch.
- **FR-008**: The system MUST offer 14-day Solo and Team trials without collecting a payment method and MUST require an explicit paid-plan choice before any paid subscription or charge is created.
- **FR-009**: An unconverted Solo or Team trial MUST return to Cloud Free without deleting data, silently charging the customer, or requiring a support request.
- **FR-010**: Plan quotas MUST be enforced server-side for web requests, queued workflows, and API requests where applicable; client-side displays MUST NOT be the only enforcement.
- **FR-011**: OCR usage MUST support a monthly quota independently from the existing daily quota representation, with atomic boundary handling and an explicit displayed period.
- **FR-012**: Customer document storage usage MUST be measured per organization and MUST prevent uploads that exceed the applicable plan limit without leaving orphaned files.
- **FR-013**: User limits MUST count current members and pending invitations consistently for Cloud Free, Solo, Team, and legacy plans.
- **FR-014**: API access MUST remain available according to the existing Community Edition contract when SaaS mode is disabled and MUST be granted to Team only in SaaS mode.
- **FR-015**: The migration MUST preserve legacy plan identifiers, existing Stripe subscriptions, billing history, organization data, and agreed prices until explicit customer plan change.
- **FR-016**: New customer signup and new plan selection MUST hide legacy plans while rejecting crafted submissions for inactive plans.
- **FR-017**: A legacy paid-plan change MUST update the existing Stripe subscription at most once and MUST not create duplicate subscriptions.
- **FR-018**: The website, hosted application, public documentation, translations, structured metadata, and application contract MUST use the same offer names, prices, quotas, and trial behavior.
- **FR-019**: Public claims about support, backups, retention, location, or availability MUST remain unpublished or explicitly qualified until operational evidence exists.
- **FR-020**: The implementation MUST include automated coverage for happy paths, quota boundaries, concurrent or repeated migrations, failed payment-provider paths, trial fallback, inactive-plan rejection, and CE compatibility.
- **FR-021**: Cloud Free signup controls MUST support aggregate cost monitoring and allow new free signups to be rate-limited or paused prospectively when the approved free-tier budget is exceeded, while existing customers receive a communicated transition.

### Key Entities

- **Customer offer**: A public commercial category with a stable name, price, target audience, quotas, entitlements, trial behavior, support boundary, and publication state.
- **Plan record**: A persisted implementation of an offer or legacy plan, with stable identifier, internal slug, price, quotas, features, Stripe price linkage, and active/public status.
- **Subscription**: An organization's entitlement assignment with local lifecycle state, trial dates, external billing linkage, and historical plan relationship.
- **Quota usage**: The per-organization usage for invoices, OCR scans, members/invitations, and customer document storage within the relevant period or lifetime.
- **Offer migration**: An idempotent transition from legacy Free/Starter/Business records to the new Cloud Free/Solo/Team public contract without rewriting billing history.

## Success Criteria

### Measurable Outcomes

- **SC-001**: In all four supported locales, 100% of visible customer-facing pricing surfaces show Community Edition, Cloud Free, Solo at CHF 15/month, and Team at CHF 39/month with no stale active-plan price.
- **SC-002**: 100% of Cloud Free HTTP-level quota tests reject the sixth invoice in a calendar month, the sixth monthly OCR scan, storage beyond 250 MB, a second organization, and a second member or pending invitation without partial persistence.
- **SC-003**: 100% of Solo and Team trial expiry tests result in Cloud Free access with no paid charge and preserved organization data.
- **SC-004**: 100% of seeded legacy subscriptions retain their plan identifier, Stripe linkage, price, billing history, and organization data after an idempotent migration.
- **SC-005**: 100% of explicit legacy-to-new paid plan changes update one existing external subscription and create zero duplicate subscriptions.
- **SC-006**: Across representative CE and SaaS test configurations, Community Edition API access remains unchanged and Cloud Free/Solo API calls are denied while Team API calls succeed.
- **SC-007**: The focused API, EE plugin, website, and documentation checks pass, including all four locale translation parity checks and the affected frontend builds.
- **SC-008**: A reviewer can identify the price, limits, trial result, export path, and distinction between CE and Cloud Free in under three minutes without contacting support.

## Assumptions

- Existing Laravel, PostgreSQL, Redis, Inertia.js, Vue, Next.js, Stripe, and deployment conventions remain in place.
- The current internal slugs `free`, `starter`, and `business` are retained where needed for compatibility; public Solo and Team records may use new canonical slugs while legacy paid records remain addressable.
- One SaaS subscription covers one organization at launch; fiduciary multi-client pricing is out of scope for this feature.
- Cloud Free document storage covers customer-uploaded receipt and invoice-justificatif documents. Generated PDFs, logos, exports, backups, logs, and temporary processing files are excluded unless a later policy says otherwise.
- OCR quota usage counts accepted scan submissions, including scans that later fail processing, to prevent retry-based quota bypass.
- Prices are monthly, in CHF, and tax treatment follows the existing legal and checkout policy after it is verified; annual billing is out of scope.
- Existing customers on legacy paid plans are not silently moved to CHF 15 or CHF 39.
- The mutualized production server remains the launch infrastructure; the feature does not require a dedicated-host migration.
- The feature does not add a fiduciary service, tax advice, advertising inside Gäld, data resale, or a new payment provider.
