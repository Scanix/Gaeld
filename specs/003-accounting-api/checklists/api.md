# API Requirements Quality Checklist: Community Accounting API

**Purpose**: Unit tests for the clarity, completeness, and consistency of the API requirements
**Created**: 2026-08-21
**Feature**: [Community Accounting API](../spec.md)
**Audience**: Reviewer before implementation and release planning

## Requirement Completeness

- [ ] CHK001 Are the external actors, Community Edition availability, and subscription boundary explicit? [Completeness, Spec §FR-001]
- [ ] CHK002 Are token creation, organization scope, expiry, revocation, and membership requirements defined? [Completeness, Spec §FR-002]
- [ ] CHK003 Are read, create, post, reverse, delete, invoice, expense, banking, and import abilities distinguished? [Completeness, Spec §FR-003]
- [ ] CHK004 Does the organization-isolation requirement cover every resource named in the feature? [Completeness, Spec §FR-004]
- [ ] CHK005 Is the account-code identity rule explicit for both requests and responses? [Clarity, Spec §FR-005]
- [ ] CHK006 Is the required draft-versus-posted choice unambiguous for journal-entry creation? [Clarity, Spec §FR-006]
- [ ] CHK007 Are amount precision, one-sided debit/credit lines, balancing, and minimum line count specified? [Completeness, Spec §FR-007]
- [ ] CHK008 Are closed fiscal years and other accounting-period boundaries addressed? [Completeness, Spec §FR-008]
- [ ] CHK009 Does the specification identify which existing accounting rules must remain authoritative? [Consistency, Spec §FR-009]

## Requirement Clarity

- [ ] CHK010 Are the public identifier, source metadata, totals, status, and linked-document fields defined for journal-entry responses? [Clarity, Spec §FR-010]
- [ ] CHK011 Are list filters, inclusive date semantics, pagination limits, and empty results specified? [Clarity, Spec §FR-011]
- [ ] CHK012 Are posting, reversal, deletion, immutability, and concurrent-transition outcomes distinct? [Clarity, Spec §FR-012, Spec §FR-013, Spec §FR-014]
- [ ] CHK013 Is the optional idempotency-key behavior and natural-reference fallback defined for every mutation category? [Ambiguity, Spec §FR-015]
- [ ] CHK014 Is the conflict behavior for key reuse with a changed payload explicitly defined? [Clarity, Spec §FR-016]
- [ ] CHK015 Are duplicate accounting references and idempotency conflicts distinguishable to clients? [Clarity, Spec §FR-017]
- [ ] CHK016 Are all protocol error classes mapped to stable response semantics? [Completeness, Spec §FR-018]

## Requirement Consistency

- [ ] CHK017 Are Community Edition default activation, the installation kill switch, and token authorization consistent across the spec, plan, and contract? [Consistency, Spec §FR-001, Plan §Rollout]
- [ ] CHK018 Are invoice, expense, and CAMT.053 workflows clearly separated from generic journal-entry creation? [Consistency, Spec §FR-019, Spec §FR-021]
- [ ] CHK019 Do the public-identifier requirement and the account-code integration rule avoid exposing internal database identifiers? [Consistency, Spec §FR-005, Spec §FR-024]
- [ ] CHK020 Are the documented rate limit and retry metadata consistent with the stated idempotency behavior? [Consistency, Spec §FR-015, Spec §FR-023]

## Acceptance Criteria Quality

- [ ] CHK021 Can each success criterion be evaluated without relying on an unspecified client implementation? [Measurability, Spec §SC-001, Spec §SC-002]
- [ ] CHK022 Are the no-duplicate and cross-organization security outcomes measurable for every mutation and resource? [Measurability, Spec §SC-003, Spec §SC-004]
- [ ] CHK023 Does the release criterion define what documentation must match the released contract? [Completeness, Spec §SC-005, Spec §SC-007]
- [ ] CHK024 Does the compatibility criterion identify the existing web and API behavior that must remain unchanged? [Clarity, Spec §SC-008]

## Scenario and Edge-Case Coverage

- [ ] CHK025 Are primary, alternate, exception, recovery, concurrency, and rate-limit scenarios all represented? [Coverage, Spec §Edge Cases]
- [ ] CHK026 Are partial invoice, expense, and CAMT.053 failures covered by explicit recovery requirements? [Recovery, Spec §Edge Cases]
- [ ] CHK027 Are malformed amounts, invalid accounts, duplicate transactions, and absent resources covered with distinct outcomes? [Exception, Spec §Edge Cases]
- [ ] CHK028 Are repeated reversal and concurrent draft-publication behaviors specified without contradictory outcomes? [Concurrency, Spec §FR-012, Spec §FR-013]

## Non-Functional and Operational Requirements

- [ ] CHK029 Are performance, pagination, rate-limit, and bounded-resource expectations quantified? [Non-Functional, Plan §Technical Context]
- [ ] CHK030 Are authentication, authorization, tenant isolation, secret handling, and audit expectations complete? [Security, Spec §FR-002, Spec §FR-003, Spec §FR-004, Spec §FR-022]
- [ ] CHK031 Are migration rollback, feature-flag rollback, compatibility, and client coordination requirements explicit? [Operations, Plan §Rollout and Operations]
- [ ] CHK032 Are monitoring fields and secret-redaction requirements defined without requiring bearer-token or payload-secret retention? [Observability, Spec §FR-022, Plan §Monitoring]

## Dependencies and Assumptions

- [ ] CHK033 Are dependencies on existing ledger services, policies, token middleware, invoice/expense actions, and bank-import rules stated? [Dependency, Spec §Assumptions, Plan §Domain Ownership]
- [ ] CHK034 Is the assumption that account codes remain unique within an organization documented and bounded? [Assumption, Plan §Data and Contract Changes]
- [ ] CHK035 Is the separation between supported CAMT.053 imports and automatic bank synchronization explicit? [Scope, Spec §Out of Scope, Spec §Assumptions]
- [ ] CHK036 Are versioning and backward-compatibility expectations defined for existing API clients? [Compatibility, Spec §Assumptions, Contract §Compatibility]

## Ambiguities and Conflicts

- [ ] CHK037 Are all terms such as "source metadata", "external reference", "natural reference", and "safe retry" defined sufficiently for an implementer and reviewer? [Ambiguity, Spec §FR-010, Spec §FR-015]
- [ ] CHK038 Does the specification explicitly identify any mutation that cannot be safely retried without an idempotency key or natural reference? [Gap, Spec §FR-015]
- [ ] CHK039 Is the first-release boundary between the journal-entry MVP and the broader business-document API explicit enough to prevent scope drift? [Scope, Spec §Assumptions, Plan §Summary]
