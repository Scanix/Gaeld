# Tasks: CE and Commercial Edition Boundary

**Input**: Design documents from `/specs/006-ce-commercial-boundary/`

**Prerequisites**: `spec.md`, `plan.md`, and the completed requirements checklist.

## Task Format

Use:

```text
- [ ] T### [P?] [US#] Description with an exact repository path
```

`[P]` means the task can run in parallel without conflicting file changes.
Tests are mandatory for new behavior in Gäld.

## Phase 1: Shared Boundary Setup

**Goal**: Establish one reviewed ownership vocabulary and public compatibility
contract before changing packaging or runtime behavior.

- [X] T001 Create the CE/commercial boundary manifest covering source ownership, runtime ownership, dependencies, migrations, translations, tests, generated artifacts, and deployment in `contract/edition-boundary.json`.
- [X] T002 [P] Define the shared CE/EE contract version and ownership metadata in `contract/app-contract.json`, `../web/contract/app-contract.json`, `../web/contract/edition-boundary.json`, and `../docs/contract/edition-boundary.json`.
- [X] T003 [P] Add a machine-readable boundary-manifest validation test in `tests/Feature/EditionBoundary/BoundaryManifestTest.php`.
- [X] T004 [P] Add a clean-release audit command that detects EE source, secrets, stale commercial configuration, deployment-only files, generated source maps, ignored/untracked files, and local work-in-progress in `scripts/qa/check-ce-artifact.sh`.

## Phase 2: Foundational Compatibility and Security

**Goal**: Make edition compatibility explicit, tenant-safe, and fail-closed before
story-specific packaging work begins.

- [X] T005 Add the CE/EE compatibility contract and supported version range in `app/Support/Contracts/EditionCompatibility.php`.
- [X] T006 Add compatibility parsing and fail-closed behavior tests in `tests/Feature/EditionBoundary/EditionCompatibilityTest.php`.
- [X] T007 [P] Add source and artifact security tests for public CE exclusions, AGPL license preservation, and the public contribution path in `tests/Security/EditionBoundary/CeArtifactSecurityTest.php`.
- [X] T008 [P] Add tenant-boundary tests proving commercial subscription and quota state cannot affect another organization in `plugins/gaeld-ee/tests/Security/EditionBoundary/CommercialIsolationTest.php`.
- [X] T009 Define the installation-level runtime mode schema and migration status fields in `database/migrations/2026_09_03_022256_create_edition_runtime_modes_table.php`, `app/Support/EditionRuntimeMode.php`, and `tests/Feature/EditionBoundary/EditionRuntimeModeTest.php`.

## Phase 3: User Story 1 - Install the Community Edition Independently (Priority: P1)

**Goal**: A clean public CE checkout builds, installs, upgrades, and runs core
workflows with EE absent.

**Independent Test**: Build a clean CE archive with EE absent and no private
credentials, run the Docker installation, and complete core accounting,
invoicing, expenses, VAT, reporting, export, and CE API smoke checks.

### Tests First

- [X] T010 [P] [US1] Add the clean CE boot, migration, route-cache, and config-cache test in `tests/Feature/EditionBoundary/CeStandaloneTest.php`.
- [X] T011 [P] [US1] Add the clean CE installation and core workflow acceptance script for accounting, invoicing, expenses, VAT, reports, import/export, and the existing CE API contract in `scripts/qa/ce-standalone-smoke.sh` and `tests/Feature/EditionBoundary/CeApiContractTest.php`.
- [X] T012 [US1] Add a test proving EE absence does not expose partial commercial routes or private error details in `tests/Security/EditionBoundary/CeFailClosedTest.php`.

### Implementation

- [X] T013 [US1] Make optional EE discovery and service registration fail closed, with incompatible-manifest coverage, in `bootstrap/providers.php`, `app/Providers/AppServiceProvider.php`, `app/Providers/PluginServiceProvider.php`, `plugins/gaeld-ee/plugin.json`, and `tests/Feature/EditionBoundary/PluginCompatibilityTest.php`.
- [X] T014 [US1] Verify that CE build, Composer, environment, and asset configuration has no dependency on private EE sources in `composer.json`, `package.json`, `vite.config.js`, and `.env.example`.
- [X] T015 [US1] Add the clean CE artifact audit to the supported verification workflow in `scripts/qa/README.md` and `RELEASE.md`.
- [X] T016 [US1] Update CE installation and contributor documentation to describe the independent product boundary and preserve the AGPL license/contribution path in `README.md`, `INSTALL.md`, and `CONTRIBUTING.md`.

**Checkpoint**: Run the clean CE build/install smoke test and the focused CE
suite with no EE source or credentials available.

## Phase 4: User Story 2 - Keep Commercial Capabilities Isolated (Priority: P1)

**Goal**: Commercial billing, SaaS, quota, trial, and administration code is
private and only intentionally supplied through the commercial distribution.

**Independent Test**: Audit a clean public CE artifact, install the EE package
from the private registry in a SaaS environment, and verify server-side feature
and tenant authorization boundaries.

### Tests First

- [X] T017 [P] [US2] Add an EE commercial-artifact privacy test in `plugins/gaeld-ee/tests/Feature/Packaging/CommercialArtifactTest.php`.
- [X] T018 [P] [US2] Add Cloud Free, Solo, Team, and CE entitlement boundary tests in `plugins/gaeld-ee/tests/Security/EditionBoundary/CommercialIsolationTest.php`.

### Implementation

- [X] T019 [US2] Add private Composer package metadata and registry coordinates without credentials in `plugins/gaeld-ee/composer.json` and `plugins/gaeld-ee/INTERNAL.md`.
- [X] T020 [US2] Record the embedded private EE frontend distribution decision in `plugins/gaeld-ee/plugin.json` and the public boundary manifest instead of publishing an unnecessary npm package.
- [X] T021 [US2] Add GitLab Composer package integrity, provenance, publication, and consumption checks in `plugins/gaeld-ee/scripts/check-package.sh`, `plugins/gaeld-ee/scripts/publish-composer-package.sh`, and `plugins/gaeld-ee/.gitlab-ci.yml`.
- [X] T022 [US2] Ensure commercial routes, migrations, translations, assets, server-side entitlements, and tenant authorization are loaded only when EE is intentionally enabled in `plugins/gaeld-ee/src/GaeldEEServiceProvider.php`, `plugins/gaeld-ee/src/Support/SubscriptionFeatureResolver.php`, `plugins/gaeld-ee/routes/web.php`, and `plugins/gaeld-ee/tests/Feature/EditionBoundary/CommercialRouteBoundaryTest.php`.
- [X] T023 [US2] Document private registry authentication through deployment-only environment configuration in `plugins/gaeld-ee/INTERNAL.md` and `plugins/gaeld-ee/.env.production.example`, with no private registry settings added to the public CE root.

**Checkpoint**: Verify that a public CE archive contains none of the EE
commercial artifact while a configured SaaS deployment can load the private
package and enforce plan entitlements server-side.

## Phase 5: User Story 3 - Release Both Editions Safely (Priority: P1)

**Goal**: CE and EE release independently with an immutable compatibility gate
that rejects incompatible pairs before traffic is switched.

**Independent Test**: Build two compatible release candidates and one
incompatible pair, then verify the compatible pair deploys and the incompatible
pair stops before activation.

### Tests First

- [X] T024 [P] [US3] Add compatible and incompatible CE/EE pair tests in `tests/Feature/EditionBoundary/CompatibilityGateTest.php`.
- [X] T025 [P] [US3] Add a rollback and migration-state safety test proving CE data and hosted organization, subscription, price, Stripe identifier, and billing history preservation in `plugins/gaeld-ee/tests/Feature/EditionBoundary/EditionRollbackTest.php`.

### Implementation

- [X] T026 [US3] Add immutable CE/EE compatibility metadata and version-range validation in `app/Support/EditionCompatibility.php` and `plugins/gaeld-ee/plugin.json`.
- [X] T027 [US3] Enforce the compatibility gate before the active release symlink changes and before private artifact activation in `app/Console/Commands/VerifyEditionCompatibilityCommand.php` and `deploy.php.example`.
- [X] T028 [US3] Make deployment consume immutable private registry artifacts, verify their digests, and record the selected pair in `deploy.php.example`, `RELEASE.md`, and `plugins/gaeld-ee/INTERNAL.md`.
- [X] T029 [US3] Add independent CE and EE release verification commands and rollback instructions in `app/Console/Commands/VerifyEditionCompatibilityCommand.php`, `RELEASE.md`, and `plugins/gaeld-ee/INTERNAL.md`.

**Checkpoint**: Verify release provenance, registry digest, migration state,
rollback pair, and pre-switch compatibility failure behavior.

## Phase 6: User Story 4 - Explain the Boundary Clearly (Priority: P2)

**Goal**: Users, contributors, and operators see the same CE/commercial
ownership model across public surfaces and all supported locales.

**Independent Test**: Compare CE README/installation, website pricing, hosted
billing docs, SaaS onboarding docs, contracts, and release guidance against the
boundary manifest in EN/FR/DE/IT.

### Tests First

- [X] T030 [P] [US4] Add four-locale CE/commercial terminology assertions in `../web/src/__tests__/edition-boundary-contract.test.ts`.
- [X] T031 [P] [US4] Add a documentation boundary consistency check in `../docs/scripts/check-edition-boundary.mjs`.

### Implementation

- [X] T032 [US4] Verify the existing public website copy distinguishes complete self-hosted CE from hosted commercial services in `../web/messages/en.json`, `../web/messages/fr.json`, `../web/messages/de.json`, `../web/messages/it.json`, `../web/src/app/[locale]/pricing/page.tsx`, and `../web/src/__tests__/edition-boundary-contract.test.ts`.
- [X] T033 [US4] Update CE installation, SaaS billing, and contributor documentation with the approved boundary in `../docs/docs/self-hosting.md`, `../docs/docs/billing.md`, `../docs/docs/getting-started-saas.md`, and `README.md`.
- [X] T034 [US4] Reconcile localized documentation boundary statements in `../docs/i18n/fr/`, `../docs/i18n/de/`, and `../docs/i18n/it/` for EN/FR/DE/IT output.
- [X] T035 [US4] Add the clean CE artifact, private EE artifact, and compatibility release checks to `../docs/README.md`, `../web/README.md`, and `RELEASE.md`.

**Checkpoint**: Run the four-locale website/docs checks and confirm no public
surface implies that CE requires a commercial subscription.

## Phase 7: Mixed Installation Migration

**Goal**: Existing CE installations that contain EE/SaaS state receive a
non-destructive, explicit migration to a supported runtime mode.

- [X] T036 [P] [US3] Add mixed-installation discovery and dry-run tests covering CE-only, EE-enabled, stale-EE, and hosted-subscription-preservation scenarios in `tests/Feature/EditionBoundary/MixedInstallationMigrationTest.php` and `plugins/gaeld-ee/tests/Feature/EditionBoundary/EditionRollbackTest.php`.
- [X] T037 [US3] Add the non-destructive mixed-installation migration command that records runtime mode and migration status in `app/Console/Commands/MigrateEditionRuntimeMode.php`.
- [X] T038 [US3] Add rollback, backup, subscription-preservation, and operator-confirmation requirements to `RELEASE.md` and `scripts/qa/README.md`.
- [X] T039 [US3] Add a clean migration rehearsal using an isolated PostgreSQL database and assert CE/EE data preservation in `scripts/qa/mixed-installation-migration.sh`.

## Phase 8: Final Verification and Rollout

- [X] T040 Run the complete EE suite and packaging/security checks through Sail and record the result in `RELEASE.md`.
- [X] T041 Run the complete CE suite by `Unit`, `Security`, and `Feature` testsuites, plus clean-install compatibility checks, through Sail and record the results in `RELEASE.md`.
- [X] T042 Run Pint and PHPStan on every changed PHP path through Sail.
- [X] T043 [P] Run web Vitest, localized Playwright pricing tests, offer checks, and production build in `../web/package.json` and `../web/tests/`.
- [X] T044 [P] Add and run the `check:edition-boundary` script, Docusaurus build, stale-offer scan, and localized documentation check in `../docs/package.json` and `../docs/scripts/check-edition-boundary.mjs`.
- [ ] T045 Perform staging acceptance with EE absent and enabled, including CE install, SaaS onboarding, billing, quotas, API denial/allowance, migration, rollback, and all four locales using `scripts/qa/staging-runner.mjs`.
- [ ] T046 Record the final boundary matrix, compatibility pair, registry artifact digests, migration evidence, and rollback reference in `../wiki/api/OFFER_READINESS_CHECKLIST.md`.
- [X] T047 Run `/speckit-converge` against the final code, `specs/006-ce-commercial-boundary/spec.md`, `specs/006-ce-commercial-boundary/plan.md`, and `specs/006-ce-commercial-boundary/tasks.md`, then resolve or explicitly defer every reported gap.

## Dependencies and Execution Order

- Phase 1 establishes the boundary manifest and blocks all implementation work.
- Phase 2 establishes compatibility, security, and mixed-installation invariants.
- User Story 1 can proceed after the foundational contract and artifact audit.
- User Story 2 depends on the CE artifact audit and shared compatibility contract.
- User Story 3 depends on the package metadata and compatibility tests.
- User Story 4 can proceed in parallel after the boundary manifest is approved.
- Phase 7 depends on the runtime-mode contract and migration safety tests.
- Phase 8 runs only after the required story checkpoints and migration rehearsal
  are complete.

## Parallel Execution Examples

### After Phase 2

- Track A: T010-T016 for clean CE boot, packaging, and installation.
- Track B: T017-T023 for private EE packaging and commercial isolation.
- Track C: T030-T035 for public website, documentation, and terminology.

### Before rollout

- Track A: T024-T029 for release compatibility and rollback.
- Track B: T036-T039 for mixed-installation migration.
- Track C: T043-T044 for web and documentation verification.
- Sequential final gates: T040-T042 after implementation changes are complete.

## Implementation Strategy

1. Freeze and review the boundary manifest before moving source files.
2. Prove a clean CE artifact and installation without EE.
3. Package EE privately and make compatibility failures fail closed.
4. Reconcile public surfaces and localized terminology.
5. Rehearse mixed-installation migration and rollback.
6. Run staging acceptance before any production deployment.

The smallest MVP is User Story 1 plus the foundational contract and artifact
security checks. Do not publish a new registry distribution or remove existing
sources until the clean CE and compatibility gates pass.

## Definition of Done

- Every task has focused automated coverage or a documented reason why coverage
  is not applicable.
- CE builds and operates independently without EE or private credentials.
- Commercial source and secrets are absent from public CE artifacts.
- Shared contracts have an owner, compatibility range, and fail-closed behavior.
- CE and EE releases are immutable, independently versioned, and compatible.
- Existing installations and hosted subscriptions have a non-destructive path.
- Website, documentation, contracts, and translations consistently explain the
  boundary.
- Full focused tests, builds, formatting, static analysis, and staging evidence
  are recorded before rollout.

## Phase 9: Convergence

The implementation satisfies the boundary, compatibility, and private registry
behavior covered by the current tasks. The following operational work remains
because it requires private registry credentials or an external staging
environment.

- [X] T048 [US2] Replace the generic artifact upload with the selected private GitLab Composer registry publication and consumer protocol, including deployment-only authentication and immutable version lookup, in `plugins/gaeld-ee/.gitlab-ci.yml`, `plugins/gaeld-ee/scripts/publish-composer-package.sh`, `plugins/gaeld-ee/INTERNAL.md`, and `deploy.php.example`.
- [X] T049 [US2] Add an opt-in integration check and protected GitLab consumer job that installs the published `gaeld/ee-plugin` artifact from the configured private Composer registry and verifies its digest and CE contract version in `plugins/gaeld-ee/scripts/check-package.sh`, `plugins/gaeld-ee/.gitlab-ci.yml`, and `plugins/gaeld-ee/tests/Feature/Packaging/CommercialArtifactTest.php`; the live check is skipped locally when private registry credentials are unavailable.
- [X] T050 [US4] Add a cross-repository synchronization check proving that the web/docs public boundary projections match the API-owned manifest version and required surfaces in `scripts/qa/check-boundary-projections.sh`, `../web/contract/edition-boundary.json`, and `../docs/contract/edition-boundary.json`.
