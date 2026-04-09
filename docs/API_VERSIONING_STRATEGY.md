# API Versioning Strategy

## Current State

- Public REST API routes are exposed under `/api/v1`.
- Authentication uses Laravel Sanctum tokens scoped to an organization.
- API access is gated behind `FEATURE_API_ACCESS`.
- The current surface is small enough that additive changes should remain the default.

## Versioning Rules

### URL Prefixing

- Major versions use the URL prefix: `/api/v1`, `/api/v2`, and so on.
- Minor and patch releases do not change the URL prefix.
- New public endpoints are added inside the current major version unless they would break an existing contract.

### What Counts As Breaking

The following require a new major version:

- Removing an endpoint.
- Renaming a route, field, enum value, or query parameter.
- Changing validation rules in a way that rejects payloads that previously succeeded.
- Changing response envelope shape or pagination format.
- Tightening authentication or authorization semantics for an existing endpoint without an opt-in path.

The following are non-breaking and stay in the current major version:

- Adding optional request fields.
- Adding response fields.
- Adding new endpoints.
- Expanding enum values when clients are expected to ignore unknown values.
- Improving server-side performance or validation messages without changing the contract.

## Release Policy

- Use semantic versioning for releases and Git tags.
- API compatibility follows the major version in the route prefix, not the application release number alone.
- A `v2.x` application release may continue to serve `/api/v1` as long as the `v1` contract remains compatible.

## Deprecation Policy

### Standard Window

- Deprecate first, remove later.
- Keep deprecated endpoints available for at least two minor releases or 90 days, whichever is longer.
- Record deprecations in `CHANGELOG.md`, release notes, and API docs.

### Signaling

- Add a deprecation note to Scribe/OpenAPI docs.
- Return an HTTP `Deprecation: true` response header for deprecated endpoints when practical.
- Return a `Sunset` header once a removal date is fixed.
- Include migration guidance in release notes.

## Introduction Process For `/api/v2`

Create a new major version only when a compatibility-preserving path is no longer realistic.

Required steps:

1. Duplicate route registration into a new `/api/v2` group.
2. Keep `/api/v1` online during the migration window.
3. Publish a field-by-field migration guide.
4. Add coverage for both versions during the overlap period.
5. Remove `/api/v1` only after the published sunset date.

## Controller And Resource Guidance

- Prefer explicit API Resources or DTO-backed transformers for all new endpoints.
- Avoid leaking internal model field names directly if they are likely to change.
- Keep pagination, filtering, and sort parameter names consistent across resources.
- Default to additive evolution inside `v1`.

## Documentation Requirements

Every public API change must update:

- `routes/api.php` when routes are added or versioned.
- Scribe annotations or API Resources that define the request and response contract.
- `CHANGELOG.md` with consumer-visible notes.
- This document if the versioning policy itself changes.

## Decision

- Keep `/api/v1` as the only public version for now.
- Introduce `/api/v2` only for deliberate breaking changes, not for routine iteration.
- Make deprecation headers and changelog entries mandatory before any removal.