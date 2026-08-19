# Gäld Product Baseline

**Audit date**: 2026-08-19

**Branch**: `develop`

**Repository state**: `v3.5.1` at `58bc577`

## Purpose

This document records the product and engineering baseline before the first
Spec Kit feature is implemented. It is evidence for future specifications, not
a retroactive specification of every historical decision.

## Product Shape

Gäld is a self-hostable Swiss accounting application for freelancers and small
businesses. The product currently includes:

- organization setup, authentication, membership, permissions, and onboarding
- invoicing, QR-Bill generation, payments, credit notes, and recurring invoices
- expenses, receipts, approval, ledger posting, and recurring expenses
- bank accounts, CAMT/MT940/CSV imports, reconciliation, and payment initiation
- chart of accounts, journal entries, VAT, budgets, reports, and year-end closing
- legal archives, opening balances, payroll, assets, migration, and exports
- CE/EE feature flags and SaaS billing integration

## Runtime Architecture

- Backend: Laravel 13, PHP 8.4, PostgreSQL, Redis, and Horizon.
- Domain organization: `app/Domains/{Accounting,Api,Assets,Banking,Contacts,Expenses,Invoicing,Migration,Organizations,Payroll,Reporting,Users}`.
- Frontend: Inertia.js v3 and Vue 3 under `resources/js/Pages` and `resources/js/Components`.
- Cross-cutting tenant context: `CurrentOrganization` and `BelongsToOrganization`.
- Ledger writes: `LedgerService`.
- Ledger reads: `LedgerQueryService`.
- Verification: PHPUnit feature/unit tests, frontend build, Pint, PHPStan, and CI.

## Evidence Quality

The repository contains broad automated feature coverage, including accounting
invariants, fiscal years, invoices, payments, expenses, reconciliation,
reports, onboarding, payroll, and organization security.

Checked-in browser reports provide useful historical evidence, but they were run
against earlier branches and dates. There is no executable browser suite under
`tests/`, and Docker was unavailable during this audit, so current runtime
behavior was not re-run locally. The status of a workflow must therefore remain
separate from the presence of a test file.

## Baseline Decision

The product should be evolved with bounded, flow-forward specifications. The
first implementation target should address the highest-risk divergence between
custom fiscal-year behavior and calendar-year consumers. No broad rewrite or
new architectural layer is justified by this baseline alone.