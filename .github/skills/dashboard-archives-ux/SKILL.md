---
name: dashboard-archives-ux
description: >
  Implementation spec for Gäld dashboard, legal archives, and year-end UX
  improvements identified in the May 2026 user-testing session. Activate when
  fixing dashboard widget filter links, surfacing the onboarding checklist on
  the dashboard, restructuring the legal archives page, locking archived
  data, or converting the year-end closing into a multi-step wizard.
---

# Gäld Dashboard, Archives & Year-End UX — Implementation Spec

## Purpose

May 2026 browser testing surfaced five concrete UX issues:

1. Dashboard widgets ("Offene Rechnungen", "Pendente Ausgabe", overdue
   invoices) link to unfiltered list pages — users can't see what the badge
   is counting.
2. New organisations land on an empty dashboard with no guided next steps,
   even though `ChecklistService` already computes them.
3. The legal archives page paginates a flat row list then groups visually
   by year — pagination controls float "outside" the single accordion card,
   confusing users.
4. Archived journal entries / invoices / expenses are not locked — `archived_at`
   is set but the rows remain editable, defeating the Swiss CO 10-year
   immutability requirement.
5. Year-end closing is a dense single page with implicit prerequisites
   (VAT settled, balances reviewed). It works but a wizard would surface
   the gating steps.

This skill drives the fixes in priority order. Each phase is independently
shippable.

---

## Personas (recap — see `onboarding-improvements` skill for full table)

P1 New Freelancer · P1b Light Freelancer (invoicing-only) · P2 Migrating
Business · P3 SME Owner · P4 Fiduciary/Accountant.

The primary beneficiaries here are **P1, P1b, and P3** (Phases 1–2, 5) and
**P4** (Phases 3–4 — auditor / compliance).

---

## Anti-divergence Rules (read before coding)

1. **Never break existing translations.** Add new keys, do not rename
   existing ones. All 4 language files stay in sync:
   `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, `lang/it/app.php`.
2. **Reuse `ChecklistService` — do not duplicate logic.** Phase 2 only
   renders what the service returns. If filtering rules change, change them
   in the service.
3. **Filter values must match the controlled status enums** used by the
   list pages. Expenses: `pending|approved|posted`. Invoices:
   `draft|sent|paid|overdue|cancelled`. Always verify against
   `resources/js/Pages/Invoices/Index.vue` and `resources/js/Pages/Expenses/Index.vue`
   before linking.
4. **Filters use Spatie QueryBuilder format**: `?filter[status]=sent` (not
   `?status=sent`). Both `InvoiceQuery` and `ExpenseQuery` declare
   `->allowedFilters(['status', ...])`.
5. **`archived_at` is the immutability boundary.** Once set, no UPDATE or
   DELETE should be permitted on the row (Phase 4). Enforce in policies AND
   in a model `saving`/`deleting` observer — defence in depth.
6. **Never modify existing tests** without explicit approval. Add new tests.
7. **Run Pint after every PHP change**:
   `vendor/bin/sail bin pint --dirty --format agent`
8. **Run PHPStan after each phase**:
   `vendor/bin/sail bin phpstan analyse --memory-limit=2G`
9. **The legal archive is JSON-only today.** PDF export (Phase 6) is
   nice-to-have, not legally required — the JSON + SHA-256 checksum already
   satisfies CO art. 957a. Do not block Phase 4 on PDF generation.
10. **Wizard step skippability**: VAT settlement is the only HARD gate in
    Phase 5. Balance preview and reference customisation are skippable.

---

## Phase 1 — Dashboard Widget Filter Links (XS, ship first)

**Goal:** Make every dashboard CTA land on the filtered view it represents.

### Files touched

| File | Change |
|------|--------|
| `resources/js/Pages/Dashboard.vue` | Replace 3 hard-coded `href` values |

### Exact edits in `Dashboard.vue`

| Line range | Current `href` | New `href` |
|------------|----------------|-----------|
| ~334–346 (unpaid invoices card) | `/invoices` | `/invoices?filter[status]=sent` |
| ~349–355 (pending expenses card) | `/expenses` | `/expenses?filter[status]=pending` |
| Receivables aging card | `/reports/aging` | unchanged |
| Overdue invoices (any card linking there) | `/invoices` | `/invoices?filter[status]=overdue` |

> **OCR pending receipts card — deferred to Phase 2.**
> `pendingOcrScans` counts `ReceiptScan` rows (ephemeral OCR jobs polled
> by the `QuickReceiptModal`), NOT `Expense` rows with `status=pending`.
> Scans only become expenses after the user opens `/expenses/create?scan_id=…`
> and confirms the form. Filtering `/expenses?filter[status]=pending`
> therefore would NOT show them. The card link stays `/expenses` in Phase 1.
> Proper destination is built in Phase 2 (see below).

### Test file

`tests/Feature/Dashboard/DashboardWidgetLinksTest.php`
Filter: `--filter=DashboardWidgetLinksTest`

Must assert (using Inertia testing helpers and a smoke render):
- `/invoices?filter[status]=sent` resolves and the `InvoiceQuery` returns
  only invoices with `status = sent`.
- `/expenses?filter[status]=pending` resolves and returns only `pending`.
- `/invoices?filter[status]=overdue` resolves and returns overdue.

No PHP / route changes required — `InvoiceQuery::allowedFilters(['status', 'type'])`
and `ExpenseQuery::allowedFilters(['status', 'category'])` already accept these.

### Verification

Click each dashboard CTA in browser; confirm the list page filter dropdown
reflects the selected status.

---

## Phase 2 — Onboarding Checklist on Dashboard (S–M)

**Goal:** Render the existing `ChecklistService` output as a guided
"Getting started" card on the dashboard when the user hasn't completed all
items, with per-org dismiss/skip.

### Files touched

- `app/Domains/Reporting/Controllers/DashboardController.php`
- `app/Domains/Organizations/Services/ChecklistService.php` (only if filtering
  by business type is missing — see Step 2.4 of `onboarding-improvements`)
- `database/migrations/{timestamp}_add_onboarding_dismissed_at_to_organizations_table.php`
- `app/Domains/Organizations/Models/Organization.php` (cast + fillable)
- `resources/js/Pages/Dashboard.vue` (new section)
- `resources/js/Components/Dashboard/OnboardingChecklist.vue` (NEW)
- `app/Http/Controllers/OnboardingDismissController.php` (NEW, single action)
- `routes/web.php` (one POST route)
- `lang/{en,fr,de,it}/app.php`

### 2.1 — Migration

```php
Schema::table('organizations', function (Blueprint $table) {
    $table->timestamp('onboarding_dismissed_at')->nullable()->after('setup_mode');
});
```

Cast `'onboarding_dismissed_at' => 'datetime'` and add to `$fillable`.

### 2.2 — DashboardController injection

```php
use App\Domains\Organizations\Services\ChecklistService;

public function index(
    CurrentOrganization $currentOrg,
    DashboardService $dashboardService,
    ChecklistService $checklists,
): Response {
    $org = $currentOrg->get();
    $metrics = $dashboardService->metrics($org->id);

    $checklist = $checklists->checklist($org->id);
    $showChecklist = $org->onboarding_dismissed_at === null
        && $this->hasIncompleteItems($checklist);

    return Inertia::render('Dashboard', array_merge($metrics, [
        // ...existing props (isEmptyState, hasExportModule)...
        'checklist' => $showChecklist ? $checklist : null,
    ]));
}

private function hasIncompleteItems(array $checklist): bool
{
    foreach (['getting_started', 'accounting'] as $tier) {
        foreach ($checklist[$tier] ?? [] as $item) {
            if (! ($item['done'] ?? true)) {
                return true;
            }
        }
    }
    return false;
}
```

### 2.3 — Dismiss route + controller

```php
// routes/web.php
Route::post('/onboarding/dismiss', OnboardingDismissController::class)
    ->name('onboarding.dismiss')
    ->middleware(['auth', 'verified']);
```

```php
// app/Http/Controllers/OnboardingDismissController.php
final class OnboardingDismissController extends Controller
{
    public function __invoke(CurrentOrganization $currentOrg): RedirectResponse
    {
        $org = $currentOrg->get();
        $this->authorize('update', $org);
        $org->update(['onboarding_dismissed_at' => now()]);
        return back();
    }
}
```

### 2.4 — Vue component `OnboardingChecklist.vue`

Props: `checklist: { getting_started: [...], accounting: [...] } | null`.

Layout:
- Card with title `t('checklist_title')` and a small "X" dismiss button
  (calls `router.post('/onboarding/dismiss', {}, { preserveScroll: true })`).
- Two collapsible groups: "Getting started" (open by default) and
  "Accounting" (collapsed by default).
- Each item renders:
  - Checkbox-style icon (`Check` from lucide if `done`, `Circle` otherwise)
  - Translated label `t('checklist_' + item.key)`
  - If `!done && item.href`, render a `<Link :href="item.href">` "Aller →"
    arrow link aligned right.
- Progress: `{{ completedCount }} / {{ totalCount }}` at the top.

### 2.5 — Dashboard.vue mount point

Insert the component ABOVE the empty-state block and BELOW the summary cards:

```html
<OnboardingChecklist v-if="checklist" :checklist="checklist" class="mt-6" />
```

### 2.6 — Translation keys (add in checklist section)

| Key | EN | FR | DE | IT |
|-----|----|----|----|----|
| `checklist_title` | "Getting started" | "Pour bien démarrer" | "Erste Schritte" | "Per iniziare" |
| `checklist_progress` | "{completed} of {total} complete" | "{completed} sur {total} terminés" | "{completed} von {total} erledigt" | "{completed} di {total} completati" |
| `checklist_dismiss` | "Dismiss" | "Masquer" | "Ausblenden" | "Nascondi" |
| `checklist_go` | "Go" | "Aller" | "Öffnen" | "Apri" |
| `checklist_section_getting_started` | "Essentials" | "Essentiels" | "Grundlagen" | "Essenziali" |
| `checklist_section_accounting` | "Accounting lifecycle" | "Cycle comptable" | "Buchhaltungs­zyklus" | "Ciclo contabile" |

Plus one key per item key returned by `ChecklistService` — verify the keys
returned and add `checklist_{key}` translations for any missing.

### 2.7 — Tests

`tests/Feature/Dashboard/OnboardingChecklistTest.php`
Filter: `--filter=OnboardingChecklistTest`

- New org with no profile / no accounts → `checklist` prop is non-null,
  `getting_started[0].done` is false.
- Org with `onboarding_dismissed_at` set → `checklist` prop is null.
- POST `/onboarding/dismiss` sets the timestamp.
- Org where every item is `done` → `checklist` prop is null
  (`hasIncompleteItems` returns false).

---

## Phase 3 — Legal Archives Page Restructure (S)

**Goal:** Eliminate the "Page 1 / 9 — Suivant" pagination orphan. Paginate
by **fiscal year**, not by individual archive row. Show year-level aggregate
stats; load per-year rows on accordion expand.

### Files touched

- `app/Domains/Accounting/Controllers/LegalArchiveController.php`
- `resources/js/Pages/Accounting/Archives/Index.vue`
- `lang/{en,fr,de,it}/app.php` (new aggregate-label keys)

### 3.1 — Controller changes

Replace `paginate(50)` with two queries:

```php
public function index(CurrentOrganization $currentOrg): Response
{
    $this->authorize('viewAny', LegalArchive::class);

    // Year-level summary (always loaded)
    $years = LegalArchive::query()
        ->select('fiscal_year')
        ->selectRaw('COUNT(*) as total_count')
        ->selectRaw('SUM(CASE WHEN verified_at IS NOT NULL THEN 1 ELSE 0 END) as verified_count')
        ->selectRaw('MIN(expires_at) as earliest_expiry')
        ->groupBy('fiscal_year')
        ->orderByDesc('fiscal_year')
        ->get();

    return Inertia::render('Accounting/Archives/Index', [
        'years' => $years,
    ]);
}

public function forYear(int $year, CurrentOrganization $currentOrg): JsonResponse
{
    $this->authorize('viewAny', LegalArchive::class);

    $items = LegalArchive::query()
        ->where('fiscal_year', $year)
        ->orderBy('document_type')
        ->orderByDesc('archived_at')
        ->get();

    return response()->json(['items' => $items]);
}
```

Add the route in `routes/web.php`:

```php
Route::get('/accounting/archives/year/{year}', [LegalArchiveController::class, 'forYear'])
    ->whereNumber('year')
    ->name('accounting.archives.year');
```

### 3.2 — Vue changes

`Accounting/Archives/Index.vue`:

- Replace `archivesByYear` + `pagination` props with `years` (array of
  `{ fiscal_year, total_count, verified_count, earliest_expiry }`).
- Add `loadedItems = ref({})` keyed by year.
- On accordion expand, if `!loadedItems[year]`, fetch via
  `useHttp` (Inertia v3) from `/accounting/archives/year/{year}` and store.
- Render aggregate in the closed accordion header: badge "N",
  "✓ M verified", "Expires {date}".
- REMOVE the bottom pagination block entirely.

### 3.3 — Tests

`tests/Feature/Accounting/LegalArchiveIndexTest.php`
Filter: `--filter=LegalArchiveIndexTest`

- Index returns `years` keyed by `fiscal_year` with correct aggregate counts.
- `GET /accounting/archives/year/2025` returns the items for that year only.
- Years from other organizations are excluded (global scope check).

---

## Phase 4 — Lock Archived Records (S–M)

**Goal:** Once a journal entry / invoice / expense / salary slip has
`archived_at IS NOT NULL`, block any UPDATE or DELETE — return 422 with a
clear error, OR a flash error from the controller. Enforce in two layers:

1. **Policies** (`update`, `delete`) — refuse if `archived_at` is set.
2. **Model observers** (`saving`, `deleting`) — throw
   `App\Exceptions\ArchivedRecordException` as a safety net for code paths
   that bypass policies (jobs, console, raw `->save()`).

### Files touched

- NEW `app/Exceptions/ArchivedRecordException.php`
- NEW `app/Support/Observers/LocksArchivedRecord.php` (trait + observer)
- `app/Domains/Accounting/Policies/JournalEntryPolicy.php`
- `app/Domains/Invoicing/Policies/InvoicePolicy.php`
- `app/Domains/Expenses/Policies/ExpensePolicy.php`
- `app/Domains/Payroll/Policies/SalarySlipPolicy.php`
- `app/Providers/AppServiceProvider.php` (register observers)
- `lang/{en,fr,de,it}/app.php` (one new key)

### 4.1 — Exception

```php
namespace App\Exceptions;

class ArchivedRecordException extends \RuntimeException
{
    public static function locked(string $documentType, string $id): self
    {
        return new self(__('app.archived_record_locked', [
            'type' => $documentType,
            'id' => $id,
        ]));
    }
}
```

Translation:
- EN: `'This {type} (ID {id}) is legally archived and cannot be modified.'`
- FR: `'Ce {type} (ID {id}) est archivé légalement et ne peut pas être modifié.'`
- DE: `'Dieses {type} (ID {id}) ist rechtlich archiviert und kann nicht geändert werden.'`
- IT: `'Questo {type} (ID {id}) è archiviato legalmente e non può essere modificato.'`

### 4.2 — Observer

```php
namespace App\Support\Observers;

use App\Exceptions\ArchivedRecordException;
use Illuminate\Database\Eloquent\Model;

class LocksArchivedRecord
{
    public function __construct(private string $documentType) {}

    public function updating(Model $model): void
    {
        if ($this->isLocked($model) && ! $model->isDirty('archived_at')) {
            throw ArchivedRecordException::locked(
                $this->documentType,
                (string) $model->getKey(),
            );
        }
    }

    public function deleting(Model $model): void
    {
        if ($this->isLocked($model)) {
            throw ArchivedRecordException::locked(
                $this->documentType,
                (string) $model->getKey(),
            );
        }
    }

    private function isLocked(Model $model): bool
    {
        $original = $model->getOriginal('archived_at');
        return $original !== null;
    }
}
```

Note: `! $model->isDirty('archived_at')` lets the LegalArchivingService
write the `archived_at` timestamp itself; once written, subsequent updates
are blocked.

### 4.3 — Register observers

```php
// AppServiceProvider::boot()
JournalEntry::observe(new LocksArchivedRecord('journal_entry'));
Invoice::observe(new LocksArchivedRecord('invoice'));
Expense::observe(new LocksArchivedRecord('expense'));
SalarySlip::observe(new LocksArchivedRecord('salary_slip'));
```

### 4.4 — Policy gates

Add to each policy's `update` and `delete`:

```php
public function update(User $user, JournalEntry $entry): bool
{
    if ($entry->archived_at !== null) {
        return false;
    }
    // ...existing checks...
}
```

### 4.5 — Tests

`tests/Feature/Accounting/ArchivedRecordLockTest.php`
Filter: `--filter=ArchivedRecordLockTest`

For each of the 4 models:
- An archived row cannot be updated via the model (`updating` event
  throws).
- An archived row cannot be deleted via the model.
- An archived row's policy `update`/`delete` returns false.
- A non-archived row remains editable.
- Setting `archived_at` for the first time (`isDirty('archived_at')`) is
  allowed.

---

## Phase 5 — Year-End Closing Wizard (M)

**Goal:** Convert the dense single-page closing form into a 3-step wizard
that gates on prerequisites. No backend behaviour change — only restructure
the Vue page. The POST endpoint and Action stay identical.

### Files touched

- `resources/js/Pages/Accounting/YearEndClosing.vue` (restructure)
- NEW `resources/js/Components/Accounting/YearEndWizard/StepReview.vue`
- NEW `resources/js/Components/Accounting/YearEndWizard/StepVat.vue`
- NEW `resources/js/Components/Accounting/YearEndWizard/StepConfirm.vue`
- `lang/{en,fr,de,it}/app.php` (step labels)

### Step structure

| # | Title | Content | Can advance? |
|---|-------|---------|--------------|
| 1 | "Review balances" | Income + Expense tables + Net result + a "What does this mean?" help banner | Always (informational) |
| 2 | "Outstanding invoices" | List of invoices with `status = sent` or `overdue` whose `issue_date` falls in the closing fiscal year; each row shows customer, amount, days overdue, direct link `/invoices/{id}`. Soft warning banner: "These invoices remain open and will be carried into the new year as receivables." User can mark as paid / write off from the linked page. Step is **informational only — does not block** | Always |
| 3 | "Settle VAT" | List of unsettled periods with a direct link to `/reports/vat?from_date=…&to_date=…` per period; greyed-out "Continue" button until `unsettledVatPeriods.length === 0` | Only when zero unsettled |
| 4 | "Confirm closing" | Form (date / reference / result account) + final confirm button | Submits |

### Backend addition for Step 2 — outstanding invoices

Add to `YearEndClosingController::index`:

```php
$outstandingInvoices = Invoice::query()
    ->where('organization_id', $org->id)
    ->whereIn('status', ['sent', 'overdue'])
    ->whereBetween('issue_date', [$fiscalYear->start_date, $fiscalYear->end_date])
    ->orderBy('due_date')
    ->get(['id', 'number', 'customer_id', 'issue_date', 'due_date', 'total', 'status'])
    ->load('customer:id,name');
```

Pass as `outstandingInvoices` prop. Wizard Step 2 renders them; empty array → step shows green "All invoices for this year are settled" message and auto-advances on Next.

### Reuse rules

- The controller `YearEndClosingController::index` already passes `income`,
  `expenses`, `netResult`, `unsettledVatPeriods`, `fiscalYearId` — no new
  props.
- The POST stays the same: `q.post('/accounting/year-end-closing', ...)`
  with `year`, `closing_date`, `reference`, `result_account_code`.

### UX rules

- Top-of-page stepper showing the 3 steps with current state (similar to
  the existing `Setup/Wizard.vue` for visual consistency).
- "Back" returns to previous step without losing form state. Store wizard
  step in `ref(1)` only — no router state.
- If `isYearClosed` is true at page mount, skip the wizard entirely and
  render the existing "Reopen" panel.
- Existing flash error toast handling stays.

### Translation keys

| Key | EN | FR | DE | IT |
|-----|----|----|----|----|
| `year_end_wizard_step_review` | "Review balances" | "Vérifier les soldes" | "Salden prüfen" | "Verifica saldi" |
| `year_end_wizard_step_outstanding` | "Outstanding invoices" | "Factures en souffrance" | "Offene Rechnungen" | "Fatture in sospeso" |
| `year_end_wizard_step_vat` | "Settle VAT" | "Solder la TVA" | "MWST abrechnen" | "Saldo IVA" |
| `year_end_wizard_step_confirm` | "Confirm closing" | "Confirmer la clôture" | "Abschluss bestätigen" | "Conferma chiusura" |
| `year_end_wizard_outstanding_warning` | "These invoices remain open and will carry into the new year as receivables." | "Ces factures restent ouvertes et seront reportées à l'exercice suivant comme créances." | "Diese Rechnungen bleiben offen und werden als Forderungen ins neue Jahr übertragen." | "Queste fatture rimangono aperte e saranno riportate al nuovo esercizio come crediti." |
| `year_end_wizard_outstanding_empty` | "All invoices for this fiscal year are settled." | "Toutes les factures de cet exercice sont soldées." | "Alle Rechnungen dieses Geschäftsjahres sind beglichen." | "Tutte le fatture di questo esercizio sono saldate." |
| `year_end_wizard_vat_blocker` | "Settle all VAT periods before closing the year." | "Soldez toutes les périodes de TVA avant de clôturer l'exercice." | "Bitte alle MWST-Perioden vor dem Jahresabschluss abrechnen." | "Saldare tutti i periodi IVA prima della chiusura." |
| `year_end_wizard_back` | "Back" | "Retour" | "Zurück" | "Indietro" |
| `year_end_wizard_next` | "Continue" | "Continuer" | "Weiter" | "Continua" |

### Tests

`tests/Feature/Accounting/YearEndClosingWizardTest.php`
Filter: `--filter=YearEndClosingWizardTest`

Backend behaviour unchanged — only add one smoke test asserting the existing
controller `index` action still renders successfully with the same prop
shape (regression guard).

Frontend behaviour does NOT need a unit test (no Vitest infra in this
project); manual browser walkthrough in the verification step is enough.

### Verification

1. PixelCraft-style org with unsettled VAT → wizard blocks at Step 2.
2. Settle quarters → return to wizard → Step 2 "Continue" enables.
3. Step 3 confirm → success flash → FY status `closed`, BOUCL entry created.

---

## Phase 6 — Legal Archive PDF Export (M–L, REQUIRED)

**Goal:** Generate a per-fiscal-year PDF bundle (general journal, balance
sheet, **P&L / compte de résultat**) alongside the existing JSON archive.
**Required for Swiss tax filing** — the user submits the P&L PDF to the
cantonal tax authority every year. JSON + checksum satisfies CO art. 957a
for immutability, but the tax authority requires a human-readable PDF.

### Files touched

- NEW `app/Domains/Accounting/Actions/GenerateArchivePdfAction.php`
  (uses `dompdf/dompdf`, already a dependency)
- `app/Domains/Accounting/Services/LegalArchivingService.php`
  (call action from `archiveFiscalYear`)
- NEW Blade templates under `resources/views/pdf/archive/`:
  - `journal.blade.php` — general journal (all entries for the year)
  - `balance-sheet.blade.php` — balance sheet at `end_date`
  - `pnl.blade.php` — profit & loss / compte de résultat for the year
- `app/Domains/Accounting/Controllers/LegalArchiveController.php`
  (add `downloadPdf` and `downloadYearBundle` actions)
- `resources/js/Pages/Accounting/Archives/Index.vue` (add per-year PDF
  download buttons next to the existing JSON list)
- `routes/web.php` (two routes)
- `lang/{en,fr,de,it}/app.php` (button labels)

### Implementation outline

1. `GenerateArchivePdfAction::execute(string $orgId, int $year)`:
   - Reuses existing report services (`JournalReportService`,
     `BalanceSheetService`, `IncomeStatementService`) to gather data.
   - Renders each Blade template via `Pdf::loadView(...)->output()`.
   - Writes each PDF to `archives/{orgId}/{year}/pdf/{type}-{year}.pdf`.
   - For each file, computes SHA-256 and stores a `LegalArchive` row
     with `document_type IN ('pdf_journal', 'pdf_balance_sheet', 'pdf_pnl')`.
2. Called from `LegalArchivingService::archiveFiscalYear()` AFTER the
   JSON archiving loop. Wrap in try/catch — if PDF generation fails, log
   and continue (JSON archive remains the source of truth).
3. Also expose a manual re-generation endpoint:
   `POST /accounting/archives/year/{year}/regenerate-pdfs`
   (idempotent — overwrites if older than 1 day, otherwise no-op).
4. Download endpoints:
   - `GET /accounting/archives/year/{year}/pdf/{type}` — single PDF.
   - `GET /accounting/archives/year/{year}/bundle` — ZIP of all 3 PDFs.

### Blade templates — content rules

- A4, 10mm margins, header with org name + logo + fiscal year, footer
  with page number and generation timestamp.
- Numbers right-aligned, monospace font for amounts, 2 decimal places,
  thousands separator per locale.
- Account codes shown next to labels.
- Bottom of each PDF: SHA-256 of the corresponding JSON snapshot for
  cross-verification.

### Archives page UI

In `Accounting/Archives/Index.vue`, inside the year accordion header,
add a button group BEFORE the items list:

- "Download P&L (PDF)" → primary button
- "Download balance sheet (PDF)" → secondary
- "Download journal (PDF)" → secondary
- "Download full bundle (ZIP)" → outline

### Tests

`tests/Feature/Accounting/ArchivePdfGenerationTest.php`
Filter: `--filter=ArchivePdfGenerationTest`

- Calling `GenerateArchivePdfAction` for a closed fiscal year produces
  3 PDF files at the expected paths.
- Each generated PDF has a corresponding `LegalArchive` row with valid
  SHA-256.
- Download endpoints return `application/pdf` with non-empty body.
- ZIP bundle contains exactly 3 PDFs.
- Regeneration within 1 day is a no-op (file mtime unchanged).

### Translation keys

| Key | EN | FR | DE | IT |
|-----|----|----|----|----|
| `archive_download_pnl` | "P&L (PDF)" | "Compte de résultat (PDF)" | "Erfolgsrechnung (PDF)" | "Conto economico (PDF)" |
| `archive_download_balance_sheet` | "Balance sheet (PDF)" | "Bilan (PDF)" | "Bilanz (PDF)" | "Bilancio (PDF)" |
| `archive_download_journal` | "Journal (PDF)" | "Journal (PDF)" | "Journal (PDF)" | "Libro giornale (PDF)" |
| `archive_download_bundle` | "Full bundle (ZIP)" | "Lot complet (ZIP)" | "Komplett­paket (ZIP)" | "Pacchetto completo (ZIP)" |
| `archive_regenerate_pdfs` | "Regenerate PDFs" | "Régénérer les PDF" | "PDFs neu erstellen" | "Rigenera PDF" |

---

## Phase Completion Gates

After each phase:

1. `vendor/bin/sail bin pint --dirty --format agent` reports no changes.
2. `vendor/bin/sail bin phpstan analyse --memory-limit=2G` exits 0.
3. The phase's test filter passes:
   `vendor/bin/sail artisan test --compact --filter={PhaseTestClass}`.
4. Browser smoke check (see each phase's "Verification" section).

---

## Recommended Execution Order

All six phases are required. No deferrals.

1. **Phase 1** — Widget filter links (XS, ship immediately).
2. **Phase 2** — Checklist on dashboard (highest first-mile UX impact).
3. **Phase 3** — Archives page restructure (fixes visible UI defect).
4. **Phase 6** — PDF export (REQUIRED for Swiss tax filing — promoted
   above Phase 4/5 because users need the P&L PDF now).
5. **Phase 4** — Lock archived records (compliance value).
6. **Phase 5** — Year-end wizard with outstanding-invoices step.

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `resources/js/Pages/Dashboard.vue` | Dashboard widgets (Phases 1, 2) |
| `app/Domains/Reporting/Controllers/DashboardController.php` | Dashboard Inertia controller |
| `app/Domains/Organizations/Services/ChecklistService.php` | Existing onboarding checklist (Phase 2) |
| `app/Domains/Organizations/Models/Organization.php` | Add `onboarding_dismissed_at` (Phase 2) |
| `resources/js/Pages/Accounting/Archives/Index.vue` | Archives page (Phase 3) |
| `app/Domains/Accounting/Controllers/LegalArchiveController.php` | Archives controller (Phase 3) |
| `app/Domains/Accounting/Services/LegalArchivingService.php` | Archiving service; already creates JSON + checksum |
| `app/Domains/Accounting/Models/LegalArchive.php` | Archive row model |
| `app/Domains/Accounting/Policies/*Policy.php` | Per-domain policies (Phase 4) |
| `resources/js/Pages/Accounting/YearEndClosing.vue` | Year-end page → wizard (Phase 5) |
| `app/Domains/Accounting/Controllers/YearEndClosingController.php` | Year-end controller (unchanged) |
| `app/Domains/Accounting/Actions/YearEndClosingAction.php` | Closing action (unchanged) |
| `lang/{en,fr,de,it}/app.php` | Translations (every phase touches all 4) |

---

## Related Skills

- **`onboarding-improvements`** — Phase 2 here completes Step 1.2 (empty
  state) and Step 2.4 (checklist filtering by business type). Reference
  that skill's anti-divergence rules.
- **`architectural-cleanup`** — Phase 4 should reuse the policy registration
  pattern from Issue 2. If `JournalEntry`/`Invoice`/`Expense`/`SalarySlip`
  policies don't exist yet, create them as that skill prescribes BEFORE
  adding the archived-record gate.
- **`inertia-vue-development`** — Phase 5 wizard uses Inertia v3 patterns;
  consult for `useHttp`, layout props, and form state.
