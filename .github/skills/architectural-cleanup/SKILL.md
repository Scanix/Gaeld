---
name: architectural-cleanup
description: >
  Implementation spec for Gäld architectural cleanup. Activate when working on:
  removing manual organization_id scoping, adding missing model policies,
  extracting business logic from fat controllers into Actions, the
  SubscriptionContract interface, or any refactoring identified in the
  May 2026 architectural review.
---

# Gäld Architectural Cleanup — Implementation Spec

## Purpose

Five concrete issues were identified in the May 2026 architectural review.
This skill prevents drift by documenting the precise plan, constraints, and
file references for each issue. Work through issues in priority order.

---

## Anti-Drift Rules (read before coding)

1. **`BelongsToOrganization` is the source of truth for tenant scoping.** It
   already exists in `app/Support/Traits/BelongsToOrganization.php` and is
   applied to most tenant-scoped models. Do NOT add a new global scope — work
   with the existing one.
2. **Never remove `Rule::exists()` / `Rule::unique()` org scoping in
   FormRequests.** The Eloquent global scope does not apply to raw rule
   validators. Those are necessary and must stay.
3. **Never remove org scoping in raw joins or `DB::table()` queries.** The
   global scope only fires on Eloquent builders.
4. **Do not extract CRUD into Actions unless there are real invariants.** Simple
   `::create()` / `->update()` with no business rules are acceptable inline.
   The bar is: *does the write have side-effects or enforce invariants?*
5. **Do not modify existing tests without approval.** Add new tests; don't touch
   existing assertions.
6. **Run Pint after every PHP change:**
   `vendor/bin/sail bin pint --dirty --format agent`
7. **Check PHPStan after every refactor:**
   `vendor/bin/sail bin phpstan analyse --memory-limit=2G`

---

## Issue 1 — Redundant Manual Scoping · Priority 4

### Context

`BelongsToOrganization` adds an Eloquent global scope on boot that automatically
filters by `organization_id` when `CurrentOrganization::isBound()` is true.
Despite this, 223 `.where('organization_id', ...)` calls exist across 95 files.
On models that carry the trait this is redundant noise — double-scoping that
makes the code look unsafe even where it isn't.

### Models confirmed to carry `BelongsToOrganization`

```
Account, FiscalYear, JournalEntry, VatRate, CostCenter, LegalArchive,
ConsolidationGroup, ConsolidationElimination, JournalEvent, TaxDeclaration
```

Verify with: `grep -rln "BelongsToOrganization" app/Domains --include="*.php" | grep "Models/"`

### What to remove

Remove `.where('organization_id', ...)` from:
- Controller methods querying models that have the trait
- Action classes querying models that have the trait
- Service methods querying models that have the trait

### What to keep (mandatory)

- All `Rule::exists('table', 'id')->where('organization_id', ...)` in FormRequests
- All `Rule::unique('table', 'col')->where('organization_id', ...)` in FormRequests
- All `DB::table(...)->where('organization_id', ...)` raw queries
- All queries in Jobs — jobs run outside HTTP context; `isBound()` returns false there,
  so the global scope is a no-op. Jobs must scope manually.
- `AccountQuery.php` and similar Query objects that accept an explicit `$orgId` param —
  these are called from Jobs and Services; keep explicit scoping there.

### Worst offenders (start here)

| File | Redundant calls |
|------|----------------|
| `app/Domains/Accounting/Controllers/YearEndClosingController.php` | 9 |
| `app/Domains/Banking/Controllers/ReconciliationController.php` | 5 |
| `app/Domains/Accounting/Controllers/AccountingController.php` | 2 |

### Verification

After removing a call, run the full test suite to confirm no regression:
`vendor/bin/sail artisan test --compact`

---

## Issue 2 — Missing Model Policies · Priority 1

### Context

12 controller methods perform manual ownership checks:
```php
if ($model->organization_id !== $currentOrg->id()) {
    abort(403);
}
```
These 5 models have no Policy class, causing the pattern to repeat across
controllers: `FiscalYear`, `TaxDeclaration`, `CostCenter`, `ExchangeRate`,
`ConsolidationGroup`.

### Models and affected controllers

| Model | Controller | Methods with manual check |
|-------|-----------|--------------------------|
| `FiscalYear` | `FiscalYearsController` | `update` |
| `TaxDeclaration` | `TaxDeclarationController` | `update`, `destroy` |
| `CostCenter` | `CostCenterController` | `update`, `destroy` |
| `ExchangeRate` | `ExchangeRateController` | `destroy` |
| `ConsolidationGroup` | `ConsolidationController` | `show`, `update`, `destroy` |

### Implementation steps

**For each model:**

1. Create the Policy:
   ```
   vendor/bin/sail artisan make:policy {Model}Policy --model={Model}
   ```
   File lands in `app/Domains/{Domain}/Policies/{Model}Policy.php`.

2. Implement `view`, `update`, `delete` — ownership is the only check:
   ```php
   public function view(User $user, FiscalYear $fiscalYear): bool
   {
       return $fiscalYear->organization_id === $user->currentOrganizationId();
   }
   ```
   Use `CurrentOrganization` injected via the constructor if needed, or
   resolve the org from the authenticated user.

3. Register in `AppServiceProvider::$policies`:
   ```php
   FiscalYear::class => FiscalYearPolicy::class,
   ```

4. In the controller, replace the manual check:
   ```php
   // Before
   if ($fiscalYear->organization_id !== $orgId) {
       abort(403);
   }

   // After
   $this->authorize('update', $fiscalYear);
   ```

### Test each policy

Add a feature test asserting that an org member cannot access another org's resource.
Minimum: one 403 test per model.

---

## Issue 3 — Fat Controllers with Inline Transactions · Priority 2

### Context

5 controllers manage DB transactions themselves (Action's job):

| File | Lines | Problem |
|------|-------|---------|
| `YearEndClosingController` | 355 | Transaction + entry generation + archiving inline |
| `ReconciliationController` | 353 | Inline suggestion matching and import logic |
| `ConsolidationController` | 309 | Inline consolidation + report generation |
| `TwoFactorChallengeController` | 250 | Transaction inline (auth context — lower priority) |
| `OnboardingController` | ~150 | Transaction inline |

### Execution order

#### Phase A — `YearEndClosingController` (highest priority)

The `store()` method (lines ~140–340) handles the full closing transaction
inline. `YearEndClosingAction` already exists but only wraps part of the logic.

**Target state:**
```php
public function store(StoreYearEndClosingRequest $request, CurrentOrganization $currentOrg): RedirectResponse
{
    $this->authorize('closeYear', Account::class);

    $data = $request->validated();
    $result = $this->closingAction->execute($currentOrg->get(), $data);

    return redirect()->route('accounting.fiscal-years')
        ->with('success', __('app.year_end_closing_success'));
}
```

All transaction logic moves into `YearEndClosingAction::execute()`.

#### Phase B — `ReconciliationController`

Extract suggestion scoring and import into:
- `App\Domains\Banking\Actions\ImportReconciliationAction`
- `App\Domains\Banking\Actions\SuggestMatchesAction`

#### Phase C — `ConsolidationController`

Extract into `App\Domains\Accounting\Actions\RunConsolidationAction`.

### Constraint

Do NOT extract simple CRUD writes that have no invariants. The threshold:
a controller method with a `DB::transaction()` block always warrants extraction.

---

## Issue 4 — `activeSubscription` Typed as `mixed` · Priority 3

### Context

Core code accesses `$org->activeSubscription` in 8+ places:

```
app/Domains/Organizations/Services/InvitationService.php
app/Domains/Expenses/Controllers/ExpenseReceiptController.php
app/Domains/Api/Controllers/InvoiceApiController.php
app/Domains/Invoicing/Controllers/InvoiceController.php
app/Http/Middleware/HandleInertiaRequests.php (×2)
app/Http/Middleware/EnsureActiveSubscription.php
```

The relation is injected by the EE plugin via `resolveRelationUsing()` and is
declared in `Organization` as `@property-read mixed $activeSubscription`. PHPStan
cannot reason about it; IDE completion breaks.

### Implementation

1. **Create the interface:**
   `app/Support/Contracts/SubscriptionContract.php`
   ```php
   namespace App\Support\Contracts;

   interface SubscriptionContract
   {
       public function isTrialing(): bool;
       public function isActive(): bool;
       public function isPastDue(): bool;
       public function isPaused(): bool;
       /** @return mixed The associated plan (typed loosely to avoid core→EE coupling) */
       public function getPlan(): mixed;
   }
   ```

2. **Update `Organization` PHPDoc:**
   ```php
   * @property-read \App\Support\Contracts\SubscriptionContract|null $activeSubscription
   ```

3. **Implement the interface in EE:**
   `plugins/gaeld-ee/src/Domains/Billing/Models/Subscription.php` must
   implement `SubscriptionContract`.

4. **Update call sites** to use the typed interface methods instead of
   accessing `->status` directly.

### What NOT to do

Do not import `Plugins\GaeldEE\...` in any `app/` file. The contract lives in
core; EE implements it. Core never references EE types directly.

---

## Issue 5 — `MonthlyDepreciationJob` Org Context Documentation · Priority 5

### Context

`MonthlyDepreciationJob` fetches all `FixedAsset` records globally with no
`organization_id` filter. This is intentional — the global scope no-ops when
`isBound()` returns false (outside HTTP context). But it's invisible to future
developers.

### Fix

Add a comment block to `app/Domains/Assets/Jobs/MonthlyDepreciationJob.php`:

```php
// This job runs outside the HTTP request lifecycle. The BelongsToOrganization
// global scope is a no-op here (isBound() returns false), so we intentionally
// query all organisations' assets. Do NOT inject CurrentOrganization here.
```

No functional change. Low risk.

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `app/Support/Traits/BelongsToOrganization.php` | Global org scope + auto-assign on create |
| `app/Support/Contracts/FeatureResolver.php` | Feature flag interface (core) |
| `app/Support/Contracts/SubscriptionContract.php` | Target interface for EE subscription (to be created) |
| `app/Domains/Organizations/Services/CurrentOrganization.php` | Request-scoped org holder |
| `app/Providers/AppServiceProvider.php` | Policy registration |
| `app/Domains/Accounting/Controllers/YearEndClosingController.php` | Primary fat controller target |
| `app/Domains/Accounting/Actions/YearEndClosingAction.php` | Target action (extend, not replace) |
| `app/Domains/Banking/Controllers/ReconciliationController.php` | Phase B fat controller |
| `app/Domains/Accounting/Controllers/ConsolidationController.php` | Phase C fat controller |
| `plugins/gaeld-ee/src/Domains/Billing/Models/Subscription.php` | EE model to implement SubscriptionContract |

---

## Execution Checklist

- [ ] **Issue 2** — 5 policies created, registered, inline checks removed
- [ ] **Issue 3 Phase A** — `YearEndClosingAction` carries full transaction logic
- [ ] **Issue 4** — `SubscriptionContract` interface in core, implemented in EE
- [ ] **Issue 1** — Redundant `.where('organization_id')` removed from Eloquent-backed queries
- [ ] **Issue 3 Phase B** — `ReconciliationController` extracted
- [ ] **Issue 3 Phase C** — `ConsolidationController` extracted
- [ ] **Issue 5** — Comment added to `MonthlyDepreciationJob`
