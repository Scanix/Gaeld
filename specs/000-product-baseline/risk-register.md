# Risk Register

| ID | Risk | Severity | Likelihood | Response |
|---|---|---:|---:|---|
| RISK-001 | Long fiscal years produce incomplete archives, exports, PDFs, or VAT closing checks | High | High for long-year organizations | Specify and test one date-range contract across all consumers |
| RISK-002 | A draft journal entry can be posted after its fiscal year is closed | High | Medium | Add closed-period and atomic-transition tests before refactoring |
| RISK-003 | Concurrent draft posting can duplicate state-change events | High | Medium | Define an idempotent posting transition and test contention |
| RISK-004 | Frontend fallback values can conceal missing accounting props | Medium | Medium | Add Inertia contract tests and visible diagnostics for critical payloads |
| RISK-005 | Current browser behavior is not reproducible locally while Docker is unavailable | Medium | Certain for this audit | Re-run focused Sail tests and browser journeys on the current commit |
| RISK-006 | Historical QA reports may contain stale findings | Medium | Certain | Reconcile each finding against current code and current runtime evidence |
| RISK-007 | Reconciliation and consolidation controllers remain larger than ideal | Low | Certain | Defer extraction until correctness work identifies a real invariant boundary |

## Priority Order

1. Fiscal-year boundary consistency.
2. Draft posting period integrity and concurrency.
3. Current runtime validation of the critical accounting journeys.
4. Frontend/backend contract enforcement.
5. Only then consider targeted controller cleanup.

This order preserves the product while avoiding a broad architectural rewrite.