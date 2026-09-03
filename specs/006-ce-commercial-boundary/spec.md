# Feature Specification: CE and Commercial Edition Boundary

**Feature Branch**: `feature/ce-commercial-boundary`

**Created**: 2026-09-03

**Status**: Draft

**Input**: User description: "Define and implement a clean separation between the Community Edition and the commercial edition across repositories, packaging, runtime behavior, documentation, and release operations."

## User Scenarios & Testing

### User Story 1 - Install the Community Edition Independently (Priority: P1)

As a self-hosting user, I want to install and operate the Community Edition
without access to private commercial sources, credentials, or services so that
I can rely on the open-source product as a complete standalone application.

**Why this priority**: The CE promise is only credible if a clean checkout can
be built, installed, upgraded, and used without accidental EE coupling.

**Independent Test**: Build the CE from a clean public checkout in an isolated
environment with the EE plugin absent, then complete installation, login,
accounting, invoicing, expense, VAT, reporting, export, and API smoke flows.

**Acceptance Scenarios**:

1. **Given** a clean CE checkout, **when** dependencies are installed and the
   application is configured without EE variables, **then** the build and
   installation complete without private repositories, Stripe credentials, or
   commercial-only database tables.
2. **Given** a running CE installation, **when** a user performs core accounting,
   invoicing, expenses, VAT, reports, export, and supported API workflows,
   **then** each workflow remains available and behaves according to the CE
   contract.
3. **Given** a CE installation without the EE plugin, **when** the application
   boots or caches routes/configuration, **then** no EE class, route, migration,
   translation, or asset is required for successful startup.

### User Story 2 - Keep Commercial Capabilities Isolated (Priority: P1)

As the product owner, I want commercial SaaS, billing, quotas, hosted
entitlements, and private administration isolated from the CE so that private
business logic is not distributed accidentally and commercial changes do not
weaken the open-source contract.

**Why this priority**: The separation must protect both the CE user promise and
the commercial product boundary.

**Independent Test**: Inspect a clean CE source archive and dependency graph,
then run CE and EE test/build workflows separately and verify that commercial
capabilities are available only when the EE package is intentionally enabled.

**Acceptance Scenarios**:

1. **Given** a CE source archive, **when** it is inspected or built, **then** it
   contains no private EE source, private deployment configuration, SaaS admin
   implementation, Stripe billing implementation, or commercial-only secrets.
2. **Given** a SaaS deployment with EE enabled, **when** an organization uses
   billing, hosted plans, quotas, trials, or SaaS Admin, **then** those
   capabilities are provided by the commercial boundary and remain tenant-safe.
3. **Given** a feature exists in both editions, **when** its behavior differs by
   edition, **then** the difference is represented by an explicit contract or
   feature resolver rather than an undocumented source import or client-only
   flag.

### User Story 3 - Release Both Editions Safely (Priority: P1)

As a maintainer, I want CE and commercial releases to be independently
versioned, tested, and deployable so that a commercial release cannot silently
change the public CE artifact and a CE release cannot break SaaS deployments.

**Why this priority**: The current product spans public and private repositories;
release mistakes can expose source, break production, or create incompatible
runtime contracts.

**Independent Test**: Produce a CE release and a matching EE release from clean
branches, verify their compatibility contract, build each artifact, and deploy
a disposable environment with either edition enabled or absent.

**Acceptance Scenarios**:

1. **Given** a CE release candidate, **when** its release checks run, **then**
   the public artifact, tag, changelog, and installation instructions agree and
   do not require EE.
2. **Given** an EE release candidate, **when** its private release checks run,
   **then** the EE tag, compatibility declaration, migrations, commercial tests,
   and deployment reference agree with the selected CE baseline.
3. **Given** incompatible CE and EE versions, **when** deployment is attempted,
   **then** the compatibility check fails before application traffic is switched
   to the new release.

### User Story 4 - Explain the Boundary Clearly (Priority: P2)

As a user or contributor, I want documentation to explain what belongs to the
CE and what belongs to the hosted commercial edition so that I can choose,
install, contribute to, or operate the correct product without ambiguity.

**Why this priority**: A technical separation that is not reflected publicly
will continue to produce incorrect expectations and support issues.

**Independent Test**: Review the CE README, installation guide, website, hosted
billing documentation, contributor guidance, and release runbooks against one
approved boundary matrix in all supported locales where applicable.

**Acceptance Scenarios**:

1. **Given** the CE documentation, **when** a reader looks for included
   functionality, licensing, installation, and support, **then** the CE is
   described as complete, self-hostable, and independent of a SaaS subscription.
2. **Given** hosted documentation, **when** a reader compares hosted plans and
   commercial capabilities, **then** the documentation identifies the EE-owned
   features and does not imply that CE users need private access.
3. **Given** a contributor or operator, **when** they follow the release or
   development instructions, **then** public and private repository steps,
   credentials, artifacts, and deployment responsibilities are unambiguous.

### Edge Cases

- A CE checkout is built with stale EE environment variables still present.
- An EE plugin is absent, disabled, or present at an incompatible version.
- A shared contract changes while CE and EE are released on different schedules.
- A commercial-only migration is accidentally included in the CE migration path.
- A public source archive, frontend bundle, source map, or generated contract
  leaks private EE names or implementation details.
- A self-hosted CE upgrade encounters SaaS tables or configuration from a prior
  installation.
- A SaaS deployment rolls back CE while retaining an EE database migration.
- A feature is useful to CE users but commercially metered in hosted mode.
- Documentation, website, application translations, and release notes disagree
  about whether a capability is CE or commercial.
- A repository contains ignored, generated, or stale private files that are not
  part of the intended release but influence a local build.

## Requirements

### Functional Requirements

- **FR-001**: The project MUST define one authoritative CE/commercial boundary
  matrix covering source ownership, runtime ownership, licensing, packaging,
  dependencies, data, feature flags, tests, documentation, and deployment.
- **FR-002**: The CE MUST build, install, upgrade, and run its core workflows
  without private commercial source, credentials, services, or database schema.
- **FR-003**: Commercial-only source MUST remain outside the public CE artifact,
  including SaaS billing, Stripe integration, hosted plan enforcement, SaaS
  administration, and private deployment configuration.
- **FR-004**: Shared CE/EE contracts MUST be explicit, versioned, and testable;
  CE MUST provide safe defaults when EE is absent.
- **FR-005**: The runtime MUST fail safely when EE is absent or incompatible,
  without exposing private implementation details or partially enabling a
  commercial workflow.
- **FR-006**: CE and EE MUST have independent release identifiers, compatibility
  references, changelogs, and verification commands.
- **FR-007**: A release process MUST prevent deployment of an incompatible CE/EE
  pair before traffic is switched to the candidate.
- **FR-008**: Commercial features MUST be authorized server-side at the EE
  boundary; client-side visibility MUST NOT define commercial access.
- **FR-009**: Organization, subscription, quota, and billing data MUST remain
  isolated from CE data paths and MUST preserve tenant authorization.
- **FR-010**: Documentation and public product surfaces MUST describe the CE as
  complete and self-hostable and identify commercial capabilities accurately.
- **FR-011**: Clean-release checks MUST detect private source, secrets, stale
  commercial configuration, generated artifacts, and accidental cross-repository
  dependencies before publication.
- **FR-012**: The implementation MUST define a compatibility and migration path
  for existing CE installations, existing hosted organizations, and existing
  commercial subscriptions without involuntary plan or data changes.
- **FR-013**: The repository workflow MUST identify generated, ignored, and local
  work-in-progress files so they cannot silently influence release artifacts.
- **FR-014**: The separation MUST preserve the AGPL obligations and public
  contribution path of the CE while keeping private commercial code private.

### Key Entities

- **Community Edition boundary**: The source, runtime, license, package, and
  operational contract that is public, self-hostable, and complete.
- **Commercial Edition boundary**: The private source and hosted operational
  contract for SaaS billing, subscriptions, metering, quotas, commercial
  entitlements, and SaaS administration.
- **Shared contract**: An explicit interface, schema, route, event, or data
  contract used by both editions without transferring ownership of commercial
  behavior into CE.
- **Release pair**: A compatible CE release and EE release identified by
  immutable refs, compatibility metadata, tests, and deployment instructions.
- **Boundary matrix**: The reviewed inventory mapping each feature, file family,
  dependency, migration, translation, test, and operational surface to CE,
  commercial, or shared ownership.

## Success Criteria

### Measurable Outcomes

- **SC-001**: A clean CE checkout completes installation and core smoke tests in
  an environment with EE absent and no private credentials.
- **SC-002**: A source and artifact audit finds zero commercial-only source files,
  secrets, private deployment files, or commercial-only migrations in the CE
  release artifact.
- **SC-003**: Every shared contract has an owning repository, compatibility
  reference, and automated check before a coordinated release is published.
- **SC-004**: An incompatible CE/EE release pair is rejected before traffic is
  switched, with a diagnostic that identifies the compatibility failure without
  exposing secrets.
- **SC-005**: Core CE workflows remain available without EE, while commercial
  workflows remain unavailable or safely defaulted when EE is absent.
- **SC-006**: The CE README, installation guide, website, commercial billing
  documentation, and release runbooks contain no contradictory ownership or
  subscription requirement across the supported locales.
- **SC-007**: A reviewer can determine the ownership and release responsibility
  of every in-scope feature family from the boundary matrix in under ten
  minutes.

## Assumptions

- The existing Laravel application, EE plugin boundary, Next.js website,
  Docusaurus documentation, and separate release repositories remain in use.
- Core accounting, invoicing, expenses, VAT, reporting, export, and the existing
  CE API remain part of the complete CE unless the product owner explicitly
  changes that decision.
- Hosted billing, Stripe, SaaS quotas, hosted trials, SaaS Admin, and private
  deployment operations remain commercial by default.
- No existing hosted subscription or self-hosted CE installation is migrated
  destructively as part of the boundary definition alone.
- The first implementation should prefer explicit contracts and packaging
  checks over a broad source-tree rewrite.
- **[NEEDS CLARIFICATION: Should commercial features be distributed as a private
  EE plugin archive, a private package registry artifact, or another mechanism?]
- **[NEEDS CLARIFICATION: Which capabilities, if any, should remain present in
  the public CE source but disabled in hosted mode rather than being exclusively
  commercial?]
- **[NEEDS CLARIFICATION: Should existing CE installations with previously
  installed EE/SaaS tables receive a supported separation migration, or is a
  clean CE reinstall/upgrade path sufficient for the first release?]
