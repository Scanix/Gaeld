# Coding Standards Audit Report

> Generated: 2 April 2026
> Reference: [docs/CODING_STANDARDS.md](../CODING_STANDARDS.md)
> Scope: All files under `app/Domains/`, `database/factories/`, `tests/`

---

## Executive Summary

| Layer | Files Audited | Violations | Compliance |
|-------|--------------|------------|------------|
| Models | 34 | 46 findings in 26 files | 24% clean |
| DTOs | 46 | 19 findings in 16 files | 65% clean |
| Actions | 32 | 10 findings in 8 files | 75% clean |
| Services | 40 | 35 files missing section separators | 12% clean |
| Controllers | 56 | 8 findings in 4 files | 93% clean |
| Enums | 21 | 17 findings in 16 files | 24% clean |
| Policies | 19 | 2 files non-compliant | 89% clean |
| Requests | 50 | 0 | 100% clean |
| Queries | 9 | 0 | 100% clean |
| Factories | 9 | 0 | 100% clean |
| Tests | 133 | 2 files non-compliant | 98% clean |

---

## 1. Models (34 files)

### 1.1 Missing `Auditable` trait (19 models)

Standard: *"Every business model MUST use `Auditable` trait."*

| File | Notes |
|------|-------|
| `Accounting/Models/Budget.php` | Budget targets |
| `Accounting/Models/LegalArchive.php` | Legal compliance records |
| `Accounting/Models/LettrageLot.php` | Clearing lots |
| `Accounting/Models/TransactionLine.php` | Core accounting records |
| `Accounting/Models/VatEntry.php` | VAT detail records |
| `Accounting/Models/VatRate.php` | Business configuration |
| `Api/Models/Webhook.php` | Webhook config |
| `Api/Models/WebhookCall.php` | Delivery logs |
| `Assets/Models/DepreciationEntry.php` | Accounting records |
| `Banking/Models/BankImport.php` | Import records |
| `Banking/Models/BankMatch.php` | Reconciliation records |
| `Banking/Models/BankTransaction.php` | Financial records |
| `Banking/Models/PersonalTransactionPattern.php` | Pattern tracking |
| `Contacts/Models/ContactPerson.php` | Business contacts |
| `Expenses/Models/ExpenseCategory.php` | Configuration |
| `Invoicing/Models/InvoicePayment.php` | Payment records |
| `Organizations/Models/OrganizationInvitation.php` | Access management |
| `Payroll/Models/DeductionRate.php` | Payroll config |
| `Users/Models/User.php` | Security-critical |

### 1.2 Missing `HasUuids` — auto-increment instead of UUID (6 models)

Standard: *"Use UUIDs (HasUuids) for new entities visible to users."*

| File | Current PK |
|------|-----------|
| `Accounting/Models/Account.php` | `@property int $id` |
| `Assets/Models/DepreciationEntry.php` | `@property int $id` |
| `Banking/Models/BankAccount.php` | `@property int $id` |
| `Contacts/Models/Customer.php` | `@property int $id` |
| `Contacts/Models/Supplier.php` | `@property int $id` |
| `Invoicing/Models/RecurringInvoice.php` | `@property int $id` |

### 1.3 Missing `BelongsToOrganization` trait (3 models)

Standard: *"Every organization-scoped model MUST use BelongsToOrganization."*

| File | Has `organization_id` column? |
|------|------------------------------|
| `Api/Models/PersonalAccessToken.php` | Yes |
| `Invoicing/Models/InvoicePayment.php` | Indirectly (via Invoice) |
| `Organizations/Models/OrganizationInvitation.php` | Yes |

### 1.4 Missing `HasFactory` trait (3 models)

| File |
|------|
| `Assets/Models/FixedAsset.php` |
| `Expenses/Models/ExpenseCategory.php` |
| `Payroll/Models/DeductionRate.php` |

### 1.5 Incomplete PHPDoc `@property` tags (7 models)

Standard: *"Include @property tags for all columns."*

| File | Missing properties |
|------|-------------------|
| `Accounting/Models/TransactionLine.php` | `id`, `journal_entry_id`, `account_id`, `description` |
| `Accounting/Models/VatEntry.php` | `id`, `journal_entry_id`, `vat_rate_id` |
| `Accounting/Models/VatRate.php` | `id`, `created_at`, `updated_at` |
| `Banking/Models/BankMatch.php` | Full docblock missing |
| `Contacts/Models/ContactPerson.php` | Full docblock missing |
| `Contacts/Models/Customer.php` | `type` field |
| `Expenses/Models/Expense.php` | `payment_method` |
| `Invoicing/Models/InvoiceLine.php` | `invoice_id`, `description` |
| `Organizations/Models/Organization.php` | All standard columns |

### 1.6 Relationship order violation (1 model)

Standard: *"Define organization() first."*

| File | Issue |
|------|-------|
| `Accounting/Models/LettrageLot.php` | `account()` defined before `organization()` |

---

## 2. DTOs (46 files)

### 2.1 Not `readonly class` (11 files)

Standard: *"All DTOs are readonly class."*

All Migration domain DTOs — these extend `AbstractImportRow` which uses mutable properties:

| File |
|------|
| `Migration/DTOs/AccountImportRow.php` |
| `Migration/DTOs/ContactImportRow.php` |
| `Migration/DTOs/ExpenseImportRow.php` |
| `Migration/DTOs/FixedAssetImportRow.php` |
| `Migration/DTOs/ImportResult.php` |
| `Migration/DTOs/InvoiceImportRow.php` |
| `Migration/DTOs/JournalEntryImportRow.php` |
| `Migration/DTOs/OpeningBalanceRow.php` |
| `Migration/DTOs/ParseResult.php` |
| `Migration/DTOs/PreviewData.php` |
| `Migration/DTOs/ValidationResult.php` |

### 2.2 Update DTOs using wrong trait (2 files)

Standard: *"Update DTOs must use OmitsNullValues."*

| File | Current | Should be |
|------|---------|-----------|
| `Contacts/DTOs/UpdateContactPersonData.php` | `use MapsToSnakeCase` | `use OmitsNullValues` |
| `Expenses/DTOs/UpdateExpenseData.php` | `use MapsToSnakeCase` | `use OmitsNullValues` |

### 2.3 Update DTOs with manual null filtering instead of trait (2 files)

| File | Issue |
|------|-------|
| `Contacts/DTOs/UpdateCustomerData.php` | Has custom `toArray()` with `array_filter(..., fn($v) => $v !== null)` instead of `OmitsNullValues` trait |
| `Contacts/DTOs/UpdateSupplierData.php` | Same pattern |

### 2.4 Update DTO missing `toArray()` entirely (1 file)

| File | Issue |
|------|-------|
| `Users/DTOs/UpdateUserProfileData.php` | No `OmitsNullValues` trait, no `toArray()` method |

### 2.5 Create DTO missing `ValidatesFromArray` trait (1 file)

| File | Has | Missing |
|------|-----|---------|
| `Banking/DTOs/CreateBankAccountData.php` | `MapsToSnakeCase` | `ValidatesFromArray` |

### 2.6 Child DTOs not overriding `fromArray()` (2 files)

Standard: *"Child DTOs must override fromArray() if extending a parent."*

| File | Parent |
|------|--------|
| `Invoicing/DTOs/CreateInvoiceData.php` | Extends `InvoicePayloadData`, empty class body |
| `Invoicing/DTOs/UpdateInvoiceData.php` | Extends `InvoicePayloadData`, empty class body |

---

## 3. Actions (32 files)

### 3.1 `execute()` returns `void` instead of model (6 files)

Standard: *"Return the created/modified model."*

| File | Returns |
|------|---------|
| `Accounting/Actions/GenerateOpeningBalancesAction.php` | `void` — should return `JournalEntry` |
| `Invoicing/Actions/SendInvoiceAction.php` | `void` — should return `Invoice` |
| `Invoicing/Actions/SendInvoiceReminderAction.php` | `void` — should return `Invoice` |
| `Invoicing/Actions/SyncInvoiceLinesAction.php` | `void` on `create()` and `replace()` |
| `Payroll/Actions/GeneratePayrollRunAction.php` | `int` (count) — should return `Collection<SalarySlip>` |
| `Accounting/Actions/ImportAccountsAction.php` | `void` — should return `Collection<Account>` |

### 3.2 Returns wrong model type (1 file)

| File | Returns | Should return |
|------|---------|--------------|
| `Invoicing/Actions/RecordPaymentAction.php` | `InvoicePayment` | `Invoice` (the primary entity) |

### 3.3 Multiple public methods (1 file)

Standard: *"Actions have a single execute() method."*

| File | Issue |
|------|-------|
| `Accounting/Actions/ImportAccountsAction.php` | Has `parseFile()`, `validate()`, and `execute()` — should be a Service |

### 3.4 Returns non-model output (1 file)

| File | Returns | Notes |
|------|---------|-------|
| `Invoicing/Actions/GenerateQrInvoicePdfAction.php` | `string` (PDF binary) | Generates a PDF, not a CRUD action — may warrant exception from the norm |

---

## 4. Services (40 files)

### 4.1 Missing section separators (35 files)

Standard: *"Use `// ──────── Section Name ──────── ` comment blocks to group methods."*

Only **5 services** have section separators:
- `Invoicing/Services/InvoiceService.php`
- `Expenses/Services/ExpenseService.php`
- `Accounting/Services/LedgerService.php`
- `Reporting/Services/ReportingService.php`
- `Reporting/Services/VatReportService.php`

All other 35 services lack them.

---

## 5. Controllers (56 files)

### 5.1 Using `app(CurrentOrganization::class)` instead of method injection (4 files)

Standard: *"Inject via method parameter, access via `$currentOrg->id()`."*

| File | Methods affected |
|------|-----------------|
| `Invoicing/Controllers/InvoiceController.php` | `create()` |
| `Api/Controllers/ApiTokenController.php` | `index()`, `destroy()` |
| `Api/Controllers/OrgTokenController.php` | `index()`, `destroy()`, and others |
| `Api/Controllers/CustomerApiController.php` | `store()` |

---

## 6. Enums (21 files)

### 6.1 Missing `label()` method (15 enums)

Standard: *"Provide label() method using translation keys."*

| File |
|------|
| `Accounting/Enums/AccountType.php` |
| `Accounting/Enums/VatEntryType.php` |
| `Api/Enums/TokenType.php` |
| `Api/Enums/WebhookEvent.php` |
| `Assets/Enums/DepreciationMethod.php` |
| `Banking/Enums/BankMatchType.php` |
| `Banking/Enums/BankTransactionType.php` |
| `Banking/Enums/CamtFormat.php` |
| `Contacts/Enums/ContactType.php` |
| `Invoicing/Enums/InvoiceType.php` |
| `Invoicing/Enums/PaymentMethod.php` |
| `Invoicing/Enums/RecurrenceFrequency.php` |
| `Migration/Enums/ImportStatus.php` |
| `Migration/Enums/Platform.php` |
| `Organizations/Enums/Role.php` |

### 6.2 Wrong backed type (1 enum)

Standard: *"Always string backed."*

| File | Current | Should be |
|------|---------|-----------|
| `Banking/Enums/MatchConfidence.php` | `enum MatchConfidence: int` | `enum MatchConfidence: string` |

### 6.3 Status enum missing state machine methods (1 enum)

Standard: *"Implement canTransitionTo(), allowedTransitions() for status enums."*

| File | Issue |
|------|-------|
| `Migration/Enums/ImportStatus.php` | Has 8 status values with clear lifecycle but no `canTransitionTo()`, `allowedTransitions()`, `isEditable()`, `isDeletable()` |

---

## 7. Policies (19 files)

### 7.1 Not extending `BasePolicy` (2 files)

Standard: *"Extend App\Support\Policies\BasePolicy."*

| File | Issue |
|------|-------|
| `Organizations/Policies/OrganizationPolicy.php` | Plain class, no BasePolicy. Uses ad-hoc `$user->organizations()->where(...)` instead of `$this->belongsToOrganization()`. `viewAny()` returns `true` without organization check |
| `Users/Policies/UserPolicy.php` | Plain class, no BasePolicy. Uses `$user->resolveCurrentOrganization()` instead of `$this->hasCurrentOrganization()` |

---

## 8. Tests (133 files)

### 8.1 Feature tests missing `WithAuthenticatedOrganization` (2 files)

| File | Issue |
|------|-------|
| `Feature/Auth/AuthenticationTest.php` | Uses `RefreshDatabase` only, no `WithAuthenticatedOrganization` |
| `Feature/Auth/TenantIsolationTest.php` | Manually duplicates org setup instead of using the trait |

---

## 9. Requests, Queries, Factories

**All compliant.** 50 requests, 9 queries, 9 factories — zero violations found.

---

## Priority Matrix

### P0 — Data integrity / security risk
| # | Finding | Files | Impact |
|---|---------|-------|--------|
| 1 | OrganizationPolicy not extending BasePolicy, `viewAny()` returns `true` | 1 | Authorization gap |
| 2 | Update DTOs using `MapsToSnakeCase` instead of `OmitsNullValues` | 2 | Null values overwrite existing data |

### P1 — Architectural consistency
| # | Finding | Files | Impact |
|---|---------|-------|--------|
| 3 | Missing `Auditable` trait on business models | 19 | No audit trail on critical records |
| 4 | Missing `BelongsToOrganization` on org-scoped models | 3 | Tenant isolation gaps |
| 5 | Auto-increment PK instead of UUID on user-facing models | 6 | Enumerable IDs, inconsistent API |
| 6 | Migration DTOs not readonly | 11 | Mutability risks |

### P2 — Convention alignment
| # | Finding | Files | Impact |
|---|---------|-------|--------|
| 7 | Actions returning void/wrong type | 8 | Inconsistent API |
| 8 | Enums missing `label()` method | 15 | Incomplete i18n |
| 9 | Missing section separators in services | 35 | Readability |
| 10 | Incomplete PHPDoc on models | 9 | IDE/static analysis |
| 11 | Controllers using `app()` instead of DI | 4 | Testability |
| 12 | Tests missing standard trait | 2 | Test consistency |
| 13 | Policies not extending BasePolicy | 2 | Pattern inconsistency |
| 14 | Enums: int backed type, missing state machine | 2 | Type safety |
| 15 | Missing `HasFactory` on models | 3 | Test support |

---

## Totals

| Severity | Unique findings | Files affected |
|----------|----------------|----------------|
| **P0** | 2 | 3 |
| **P1** | 4 | 39 |
| **P2** | 9 | 80 |
| **Total** | **15 distinct categories** | **~110 fixes needed** |
