## Implementation Plan: CE and Commercial Edition Boundary

**Branch**: `feature/ce-commercial-boundary` | **Date**: 2026-09-03 | **Spec**: [spec.md](spec.md)

**Input**: Feature specification from `/specs/006-ce-commercial-boundary/spec.md`

## Summary

Define and enforce one reviewed boundary between the public Community Edition
and the private commercial Edition. The CE remains a complete, self-hostable
accounting product. The EE owns SaaS billing, Stripe, hosted plans, quotas,
trials, SaaS administration, and private deployment operations.

The implementation uses a private Composer/npm registry for commercial
distribution, explicit CE/EE contracts for shared behavior, clean artifact
audits, compatibility metadata, and a supported migration path for existing
installations that contain EE/SaaS state. CE and EE remain independently
versioned and released from their separate repositories.

## Technical Context

**Language/Version**: PHP 8.4, Laravel 13, Vue 3, Inertia.js v3, TypeScript 6,
Next.js 16, Docusaurus 3, Node.js 24 for builds.

**Primary Dependencies**: Laravel framework and Sail, PostgreSQL, Redis,
Composer 2, pnpm, Vite, PHPUnit 13, PHPStan, Pint, Playwright, Stripe SDK, the
existing EE plugin/contract mechanism, and GitLab Self-Managed Composer Package
Registry.

**Storage**: PostgreSQL application data, local document storage, Redis runtime
state, Composer/npm registry artifacts, and generated CE/web/docs build output.

**Testing**: PHPUnit through Sail for CE and EE, targeted security and contract
tests, Pint, PHPStan, Vitest, Playwright, clean CE installation checks, source
and artifact audits, and four-locale documentation/site builds.

**Target Platform**: Self-hosted Docker Compose installations for CE, the
mutualized Linux production host for SaaS, GitHub/GitLab release repositories,
and the existing web and documentation hosts.

**Project Type**: Multi-repository Laravel web application with a public CE,
private EE plugin, Next.js public website, and Docusaurus documentation.

**Performance Goals**: CE startup and build must not perform EE discovery or
private registry access. Compatibility and packaging checks must run before
traffic switch and add no request-time scans of source trees or document stores.

**Constraints**: No new dependency unless required by the selected registry;
no EE source or secret in CE artifacts; no CE-to-EE imports; no destructive
data migration; no involuntary subscription changes; server-side authorization
remains authoritative; all commands run through Sail for API work.

**Scale/Scope**: Four repositories, four supported locales, one shared CE/EE
contract family, one public CE artifact, one private commercial distribution
path, existing hosted tenants, existing self-hosted installations, and all
release/build/test/documentation surfaces.

## Constitution Check

*GATE: Must pass before implementation and be re-checked after design.*

- **Domain Integrity First**: PASS. The plan preserves organization scope,
	accounting ownership, subscription history, and immutable records.
- **Repository Conventions**: PASS. It reuses the existing plugin, feature
	resolver, contract, Sail, Composer, pnpm, and release conventions.
- **Behavior Must Be Testable**: PASS. Each boundary has a focused packaging,
	runtime, compatibility, or release test and a clean-install scenario.
- **Security and Authorization**: PASS. Private artifacts, registry access,
	tenant scope, compatibility checks, and server-side feature authorization are
	explicit requirements.
- **Small, Reviewable, Reversible Change**: PASS. The first increment adds
	manifests/checks/migrations and avoids a broad source-tree rewrite.

## Design Decisions

### Distribution

- Publish EE PHP artifacts through a private Composer registry.
- Publish an EE frontend package through a private npm registry when frontend
	code crosses the plugin boundary; otherwise record that the assets remain
	embedded in the private EE artifact. In both cases, never copy EE source into
	CE.
- Keep the private EE Git repository as the source-of-truth for EE development,
	tagging, review, and release provenance.
- Deployment consumes immutable registry versions and records the CE/EE pair.
- The selected registry is GitLab Self-Managed at
	`https://gitlab.nectoria.com/api/v4/group/nectoria/products/gaeld/-/packages/composer/packages.json`.
	Publication creates the tagged package with
	`POST /api/v4/projects/:id/packages/composer` and `tag=vX.Y.Z`; consumption
	uses Composer 2 with `gitlab-domains` and a deployment-only `gitlab-token`
	auth file.
- Registry coordinates, package contents, version immutability, publication
	provenance, consumer configuration, and credential injection are part of the
	release contract. Public CE manifests and artifacts contain neither private
	registry credentials nor a required private registry dependency.

### Ownership

- CE owns accounting, invoicing, expenses, VAT, reports, import/export,
	self-hosting, and the existing CE API contract.
- EE owns hosted SaaS, Stripe, subscriptions, hosted quotas/metering, trials,
	SaaS Admin, and private deployment operations.
- Shared interfaces live in CE-owned contracts with CE-safe defaults; EE binds
	commercial implementations without importing EE classes into CE.

### Existing installations

- Provide a non-destructive migration for CE installations containing EE/SaaS
	tables or configuration.
- Preserve CE data and make the resulting runtime mode explicit.
- Preserve existing hosted subscriptions, prices, Stripe identifiers, and
	billing history; never silently convert a customer contract.
- Support rollback to the prior compatible CE/EE pair before traffic switch.

## Boundary Matrix

| Surface | CE owner | EE/commercial owner | Verification |
|---|---|---|---|
| Core accounting and documents | `api/app`, public CE | None | Clean CE install and core tests |
| CE API contract | `api/app/Domains/Api`, `contract/` | Team SaaS entitlement only | API security and contract tests |
| Hosted plans and subscriptions | CE-safe contract only | `plugins/gaeld-ee` | EE tests and source audit |
| Stripe and checkout | None | EE Billing domain | Stripe fake/live-safe checks |
| SaaS quotas and trials | CE resolver default | EE resolver and Billing domain | Quota/trial tests |
| SaaS Admin | None | EE SaaS Admin domain | Authorization and audit tests |
| Public acquisition | `web` | Commercial claims supplied by contract | Four-locale Playwright |
| Hosted documentation | `docs` | EE operator details kept private | Docusaurus build and stale scan |
| Deployment | CE release workflow | Private EE registry/deployer | Immutable pair check |

## Implementation Phases

### Phase 0: Boundary inventory and release contract

1. Inventory CE, EE, shared, generated, ignored, and deployment-only files.
2. Define the boundary manifest and ownership vocabulary.
3. Define CE/EE compatibility metadata, supported version ranges, and failure
	 behavior before traffic switch.
4. Record the GitLab Composer registry coordinates, package contents, immutable
	 version policy, and credential flow without committing secrets.
5. Define the publication and consumption workflows in the EE repository and
	 deployment configuration, including digest verification and rollback refs.

### Phase 1: CE packaging and runtime isolation

1. Add clean CE artifact checks for EE source, secrets, deployment files, and
	 private dependencies.
2. Ensure CE boot, migrations, config cache, route cache, build, install, and
	 core workflows succeed when EE is absent.
3. Make optional EE discovery fail closed and keep CE-safe contract bindings.
4. Add mixed-installation migration checks and rollback documentation.

### Phase 2: EE packaging and compatibility

1. Add EE package metadata, GitLab Composer publication checks, and the private
	 GitLab CI publication workflow.
2. Define the EE compatibility declaration against the CE contract version.
3. Make deployment resolve immutable CE/EE Composer artifacts, verify their
	 normalized content digests, and reject mismatches before switching the active
	 release.
4. Keep EE migrations, Stripe state, and SaaS Admin data private and tenant-safe.

### Phase 3: Public surfaces and operations

1. Align README, installation, contributor, website, and docs ownership claims.
2. Add four-locale checks for CE/commercial terminology and public scope.
3. Add release runbook steps for independent CE and EE tags, registry artifacts,
	 rollback, and clean-source verification.
4. Record the migration and compatibility evidence in the readiness checklist.

### Phase 4: Verification and rollout

1. Run clean CE install/build/API/core workflow checks with EE absent.
2. Run EE tests, source audits, compatibility checks, and private package checks.
3. Validate a mixed-installation migration and a rollback pair in isolation.
4. Run web/docs localized checks and staging acceptance before production use.

## Data and Contract Design

The boundary manifest records ownership for each feature family, source path,
dependency, migration, translation group, test group, generated artifact, and
deployment step. Compatibility metadata records the CE contract version, EE
package version, supported range, required migrations, package digests, and
fail-closed behavior.

The API repository owns the complete boundary matrix. The web and docs
repositories carry a small public projection at `contract/edition-boundary.json`
with the same version and required surface identifiers, so their checks remain
independently runnable without a sibling checkout or any EE source.

Runtime mode is installation-level rather than organization-level. The planned
`edition_runtime_modes` record contains `mode` (`ce` or `ee`),
`migration_status` (`none`, `pending`, `dry_run`, `applied`, or `blocked`),
`contract_version`, nullable `ee_version`, detected EE table/configuration
summary, migration summary, and start/completion timestamps. It has no
`organization_id`; organization and subscription data retain their existing
tenant ownership.

The mixed-installation migration moves only runtime ownership metadata and
preserves CE data, EE tables, hosted organizations, subscription rows, Stripe
customer/subscription/price identifiers, prices, and billing history. The
dry-run and applied paths must prove those invariants. It does not delete EE
tables or hosted billing history. A separate cleanup or decommission action
must be explicit, authorized, audited, and reversible.

## Validation Runbook

```sh
# API / CE: run from the api/ repository root
vendor/bin/sail artisan test --compact
vendor/bin/sail bin pint --format agent
vendor/bin/sail bin phpstan analyse --memory-limit=2G
vendor/bin/sail pnpm run build

# Website: run from the api/ repository root
(cd ../web && pnpm test)
(cd ../web && pnpm run check:offer-copy)
(cd ../web && pnpm run build)

# Documentation: run from the api/ repository root
(cd ../docs && pnpm run check:edition-boundary)
(cd ../docs && pnpm run build)

# Coordinated workspace: API, web, and docs repositories checked out together
./scripts/qa/check-boundary-projections.sh
```

For an EE registry-backed consumer check, inject the read-only deploy-token
credentials and digest through a secret manager, then run the check from the
API repository. The command creates its Composer auth file under a temporary
directory and removes it on exit:

```sh
set -a
. /secure/secrets/gaeld-ee-registry.env
set +a
EE_VERIFY_REGISTRY=1 \
plugins/gaeld-ee/scripts/check-package.sh
```

The live consumer check is intentionally opt-in and must run only in a private
CI or deployment environment. No registry credential belongs in CE source,
public CI logs, or a committed `auth.json`.

Additional release checks must build from a clean CE archive with EE absent,
inspect the resulting archive and source maps, verify the registry artifact
digest, run the compatibility check for the selected CE/EE pair, and verify
that an incompatible pair is rejected before deployment.

## Rollback

Rollback uses the last known-compatible immutable CE/EE pair. Database
migrations must be forward-compatible or have a documented rollback posture;
the first implementation must not drop CE-owned data or rewrite hosted
subscription history. Registry artifacts, tags, deployment refs, and migration
state are recorded before traffic switch. A rollback must leave the runtime
mode, migration status, CE data, and hosted subscription identifiers
consistent with the selected pair.

## Complexity Tracking

No constitution violations are proposed. The four-repository coordination is
required because CE, EE, website, and documentation already have independent
release ownership; introducing a new monorepo or a second runtime abstraction
would increase rather than reduce the separation risk.