# Gäld Constitution

## Core Principles

### I. Domain Integrity First
Gäld features must preserve the accounting and organization boundaries already
defined by the domain model. Ledger mutations go through the existing accounting
services, organization-scoped data remains isolated, and immutable or archived
records are never made editable for convenience. New behavior must state which
domain owns it and which invariants it must preserve.

### II. Laravel and Repository Conventions
Use Laravel, Inertia, Vue, and the existing domain-driven structure directly and
follow the conventions already established in the repository. Reuse existing
actions, services, DTOs, policies, form requests, components, translations, and
feature-flag mechanisms before introducing new abstractions. Do not add a new
dependency or top-level architectural layer without a documented reason.

### III. Behavior Must Be Testable
Every new behavior and every behavior change must have focused PHPUnit or
frontend test coverage appropriate to its risk. Prefer realistic Laravel
integration tests with the project database and authorization boundaries over
mock-heavy tests. A feature is not complete until its tests, formatting, static
analysis, and relevant build checks pass through the repository's Sail workflow.

### IV. Security and Authorization Are Part of the Contract
Specifications and plans must define authentication, authorization, tenant
scope, validation, sensitive data handling, and failure behavior whenever they
apply. Policies and form-request authorization are mandatory at the relevant
boundary; client-side checks are never a substitute for server-side enforcement.

### V. Small, Reviewable, Reversible Change
Prefer the smallest design that satisfies the stated user outcome. Avoid
speculative features, duplicated representations, and broad refactors hidden in
feature work. Each spec must identify compatibility, migration, rollout, and
rollback concerns when relevant, and each implementation must leave the code,
specification, plan, and tasks in agreement.

## Project Constraints

- The application targets PHP 8.4, Laravel 13, PostgreSQL, Redis, Inertia.js
	v3, and Vue 3. New work must fit the existing CE/EE plugin and feature-flag
	model.
- Development and verification commands use `vendor/bin/sail`; frontend
	commands use `pnpm`.
- User-facing behavior must support the existing localization and accessibility
	conventions where applicable.
- Breaking behavior, schema changes, operational changes, and release impact
	must be called out in the plan and reflected in the changelog or runbook when
	they affect users or operators.

## Development Workflow

For new or substantial changes, use the flow-forward Spec Kit process:

1. `/speckit-specify` creates a new numbered feature directory under `specs/`.
2. `/speckit-clarify` resolves important ambiguity before technical decisions.
3. `/speckit-plan` records the implementation approach and affected surfaces.
4. `/speckit-checklist`, `/speckit-tasks`, and `/speckit-analyze` validate and
	 sequence the work.
5. `/speckit-implement` executes the approved tasks, followed by review of both
	 code and artifacts.
6. `/speckit-converge` checks the result against the artifacts and identifies
	 remaining work.

Small bug fixes may proceed directly when the change is obvious and low risk,
but the implementation must still update an existing spec or create a new one
when it changes intended product behavior.

## Governance

This constitution supplements `AGENTS.md`, `CONTRIBUTING.md`, and the existing
domain documentation. Where repository-specific rules are more restrictive,
the more restrictive rule applies. Amendments require a reviewed change to this
file with a rationale; generated Spec Kit project files may be refreshed
separately from feature artifacts under `specs/`.

**Version**: 1.0.0 | **Ratified**: 2026-08-14 | **Last Amended**: 2026-08-14
