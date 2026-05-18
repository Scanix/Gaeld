---
name: onboarding-improvements
description: >
  Implementation spec for Gäld onboarding and workflow improvements.
  Activate when implementing any step of the 4-phase plan: freelancer export
  access, guided dashboard empty state, year-end experience, or paper migration.
  Also activate when touching OrganizationModule presets, setup wizard, checklist
  service, or fiscal year notifications.
---

# Gäld Onboarding Improvements — Implementation Spec

## Purpose

Many users — especially freelancers — never complete their accounting or use
Gäld primarily to produce invoices. This spec drives UX improvements across
four phases to reduce friction, surface the right actions at the right moment,
and support the full range of personas.

---

## Personas

| ID  | Name                  | Description |
|-----|-----------------------|-------------|
| P1  | New Freelancer        | Just registered. No prior accounting knowledge. Wants to issue invoices. |
| P1b | Light Freelancer      | Uses Gäld only for invoicing. Has an external accountant. Needs to hand off the ZIP. |
| P2  | Migrating Business    | Has prior-year books. Needs to enter opening balances. |
| P3  | SME Owner             | Has employees, budgets, social charges. Uses most features. |
| P4  | Fiduciary/Accountant  | Manages multiple client orgs. Power user. |

---

## Anti-divergence Rules (read before coding)

1. **Never block invoicing/posting** due to year-end not being done. Every workflow gate is a warning, not a hard block.
2. **fiduciary_export is an accounting tool, not just a fiduciary privilege.** It must be accessible to all business types.
3. **Checklist items are filtered by business type — never deleted.** The data structure stays complete; only display is conditional.
4. **All new wizard steps are skippable.** No new hard blockers in setup flow.
5. **Notifications are opt-out** via user `notification_preferences`. New notifications must respect existing opt-out keys.
6. **Long fiscal year (23 months max)** is already enforced server-side in `FiscalYearService::MAX_DURATION_MONTHS`. Do NOT relax this. Only add a UI explanation.
7. **`historical_summary` journal entries are excluded** from year-end closing calculations. Do NOT change this.
8. **Never modify existing tests** without explicit approval.
9. **Run Pint after every PHP change**: `vendor/bin/sail bin pint --dirty --format agent`
10. **Add translations in all 4 language files**: `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, `lang/it/app.php`. Group new keys near related keys.

---

## Phase 1 — Freelancer Essentials

**Goal:** Three targeted fixes that unblock the most common failure modes for P1/P1b.

### Step 1.1 — Enable `fiduciary_export` for Freelancers

**File:** `app/Domains/Organizations/Enums/OrganizationModule.php`

**Change:** In `presets()`, under `'freelancer'`, change:
```php
'fiduciary_export' => false,
```
to:
```php
'fiduciary_export' => true,
```

**Why:** The export page is the only way a freelancer can hand off their year-end accounting to their accountant. Having it disabled by default is the single highest-friction bug for P1b persona.

**Note:** `'sme'` also has `false`. Do NOT change SME in this step (separate decision).

**Test file:** `tests/Feature/Organizations/OrganizationModulePresetsTest.php`  
**Test filter:** `--filter=OrganizationModulePresetsTest`

Test must assert:
- `freelancer` preset has `fiduciary_export = true`
- `fiduciary` preset has `fiduciary_export = true`
- `sme` preset has `fiduciary_export = false` (guard — do not change SME silently)

---

### Step 1.2 — Dashboard Empty-State CTA

**Files touched:**
- `app/Domains/Reporting/Controllers/DashboardController.php`
- `resources/js/Pages/Dashboard.vue`
- `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, `lang/it/app.php`

**Goal:** When a new organization has zero revenue, zero expenses, and no recent journal entries, show a first-action CTA block on the dashboard pointing to:
1. Create first invoice (`/invoices/create`)
2. Export for accountant (`/accounting/export`) — shown only when the org has the `fiduciary_export` module enabled (pass `hasExportModule` from the backend)

**DashboardController changes:**
```php
// Inject CurrentOrganization, already available in the controller
$org = $currentOrg->get();
$metrics = $dashboardService->metrics($orgId);

$isEmptyState = $metrics['revenue'] === '0.00'
    && $metrics['expenses'] === '0.00'
    && count($metrics['recentTransactions']) === 0;

return Inertia::render('Dashboard', array_merge($metrics, [
    'isEmptyState' => $isEmptyState,
    'hasExportModule' => $org->hasModule(OrganizationModule::FiduciaryExport),
]));
```

**Required imports in DashboardController:**
```php
use App\Domains\Organizations\Enums\OrganizationModule;
```

**Dashboard.vue — new props:**
```js
isEmptyState: { type: Boolean, default: false },
hasExportModule: { type: Boolean, default: false },
```

**Dashboard.vue — add Link import from Inertia:**
```js
import { Link } from '@inertiajs/vue3'
```

**Dashboard.vue — empty-state block (insert after `<!-- Summary Cards -->` div and before the OCR card):**
```html
<!-- Empty state: no activity yet — guide the user to their first action -->
<div
  v-if="isEmptyState"
  class="mt-6 rounded-lg border-2 border-dashed border-[hsl(var(--border))] p-8 text-center"
>
  <h3 class="text-lg font-semibold">{{ t('dashboard_empty_state_title') }}</h3>
  <p class="mt-2 text-sm text-[hsl(var(--muted-foreground))]">{{ t('dashboard_empty_state_desc') }}</p>
  <div class="mt-6 flex flex-wrap items-center justify-center gap-3">
    <Link
      href="/invoices/create"
      class="inline-flex items-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground shadow-sm hover:bg-primary/90 transition-colors"
    >
      {{ t('dashboard_create_first_invoice') }}
    </Link>
    <Link
      v-if="hasExportModule"
      href="/accounting/export"
      class="inline-flex items-center rounded-md border border-[hsl(var(--border))] bg-background px-4 py-2 text-sm font-medium hover:bg-accent transition-colors"
    >
      {{ t('dashboard_export_for_accountant') }}
    </Link>
  </div>
</div>
```

**New translation keys (add in the `// Dashboard` section):**

| Key | EN | FR | DE | IT |
|-----|----|----|----|----|
| `dashboard_empty_state_title` | "Ready to start?" | "Prêt à commencer ?" | "Bereit loszulegen?" | "Pronti per iniziare?" |
| `dashboard_empty_state_desc` | "No activity yet. Create your first invoice or record an expense to get started." | "Aucune activité pour l'instant. Créez votre première facture ou saisissez une dépense pour démarrer." | "Noch keine Aktivität. Erstellen Sie Ihre erste Rechnung oder erfassen Sie eine Ausgabe." | "Nessuna attività ancora. Crea la tua prima fattura o registra una spesa per iniziare." |
| `dashboard_create_first_invoice` | "Create my first invoice" | "Créer ma première facture" | "Erste Rechnung erstellen" | "Crea la mia prima fattura" |
| `dashboard_export_for_accountant` | "Export for my accountant" | "Exporter pour mon comptable" | "Export für meinen Buchhalter" | "Esporta per il mio contabile" |

**Test file:** `tests/Feature/Reporting/DashboardEmptyStateTest.php`  
**Test filter:** `--filter=DashboardEmptyStateTest`

Test must assert:
- A new org (no invoices, no expenses) gets `isEmptyState = true` in Inertia props
- An org with at least one invoice has `isEmptyState = false`
- `hasExportModule` matches whether the org has the module

---

### Step 1.3 — Export Page: Inclusive Help Text

**Files touched:**
- `lang/en/app.php`, `lang/fr/app.php`, `lang/de/app.php`, `lang/it/app.php`

**Goal:** Rewrite the three export help translation values to speak to freelancers handing off to their accountant, not just fiduciaries. Do NOT change translation keys — only values.

**Changes:**

`help_export_title`:
- EN: `'Your end-of-year accounting package'`
- FR: `'Votre dossier de fin d\'exercice'`
- DE: `'Ihr Jahresabschluss-Paket'`
- IT: `'Il vostro pacchetto di fine esercizio'`

`help_export_text`:
- EN: `'This export generates a ZIP archive containing all journals, reports, invoices, and VAT declarations for the selected fiscal year. Send it to your accountant at year-end — they will have everything they need.'`
- FR: `'Cet export génère un fichier ZIP contenant tous les journaux, rapports, factures et décomptes TVA de l\'exercice sélectionné. Transmettez-le à votre comptable en fin d\'exercice — il aura tout ce qu\'il lui faut.'`
- DE: `'Dieser Export generiert ein ZIP-Archiv mit allen Journalen, Berichten, Rechnungen und MWST-Abrechnungen des gewählten Geschäftsjahres. Senden Sie es am Jahresende an Ihren Buchhalter — er hat damit alles, was er braucht.'`
- IT: `'Questo export genera un archivio ZIP contenente tutti i giornali, rapporti, fatture e dichiarazioni IVA dell\'esercizio selezionato. Inviarlo al contabile a fine esercizio — avrà tutto il necessario.'`

`export_format_note`:
- EN: `'All documents are generated in PDF and CSV formats, ready to share with your accountant.'`
- FR: `'Tous les documents sont générés en PDF et CSV, prêts à être transmis à votre comptable.'`
- DE: `'Alle Dokumente werden als PDF und CSV generiert, bereit für die Übergabe an Ihren Buchhalter.'`
- IT: `'Tutti i documenti sono generati in PDF e CSV, pronti per essere consegnati al contabile.'`

**No Vue or PHP changes needed** — the Export.vue template already uses these keys via `HelpText`.

**Verification:** Visit `/accounting/export` as a freelancer-type org and confirm the HelpText reads the updated content.

---

## Phase 1 Completion Gate

All three steps pass their respective tests AND:
- `vendor/bin/sail bin pint --dirty --format agent` reports no changes
- A freelancer org can navigate to `/accounting/export` without a 403

---

## Phase 2 — Onboarding Quality

### Step 2.1 — `setup_mode` column on organizations

Add a migration: `ALTER TABLE organizations ADD COLUMN setup_mode VARCHAR(20) DEFAULT 'fresh'`  
Values: `'fresh'` (starting from scratch) | `'migrating'` (has prior-year books)  
Set during wizard step (see Step 2.2).

**Migration file:** `database/migrations/{timestamp}_add_setup_mode_to_organizations_table.php`

### Step 2.2 — Wizard: Fresh vs. Migrating Step

**File:** `resources/js/Pages/Setup/Wizard.vue`

Add a new step (after business type, before org details) asking:
- "Are you starting fresh, or migrating from another system?"
- Option A: "Starting fresh — I have no prior accounting records"
- Option B: "Migrating — I have prior-year books to enter"

Store selection in wizard state. Submit as `setup_mode` in the final POST.

The step is skippable (defaults to `'fresh'`).

### Step 2.3 — Dashboard: Fresh-Start Banner on Opening Balances Page

**File:** `resources/js/Pages/Accounting/OpeningBalances.vue`

When `org.setup_mode === 'fresh'`, show an informational banner:
> "Starting fresh? If this is your first year in Gäld, you can skip opening balances. They're only needed when migrating from another system."

With a "Skip for now" link that does nothing (it's just informational).

### Step 2.4 — Checklist: Filter by Business Type

**File:** `app/Domains/Organizations/Services/ChecklistService.php`

In the `accounting()` method, filter items by business type:
- Hide `fiduciary_export` item for orgs that don't have the module
- Hide `social_charges` item for freelancers
- Hide `assets` / `depreciation` items for freelancers
- Show all items for `fiduciary` type

**Do NOT remove items from the data structure** — filter only at display time using the org's modules and business_type.

### Step 2.5 — Wizard: VAT Hint

In the org-details wizard step, when the user selects a revenue estimate above CHF 100,000/year (or manually enters it), show a hint:
> "Swiss law requires VAT registration above CHF 100,000 annual turnover."

This is a non-blocking hint only.

### Step 2.6 — Wizard: Founding Date

Add an optional `founded_at` date field to the org-details wizard step.  
Store on `organizations.founded_at` (requires migration).  
Used to pre-populate the fiscal year start date suggestion.

---

## Phase 3 — Year-End Experience

### Step 3.1 — FiscalYearExpiredNotification

**New file:** `app/Domains/Accounting/Notifications/FiscalYearExpiredNotification.php`

Fire this notification from `FiscalYearService::markExpired()` when a fiscal year transitions to `expired` status.

Notification content:
> "Your fiscal year {year} ended on {date}. Time to close it and start your next year."

With action button: "Close Year" → `/accounting/fiscal-years/{id}/close`

Respect `notification_preferences` opt-out key: `fiscal_year_expired`.

### Step 3.2 — Schedule `markExpired()`

**File:** `routes/console.php`

Add to the schedule:
```php
Schedule::call(fn () => app(FiscalYearService::class)->markExpiredAll())->daily()->at('06:00');
```

This requires `FiscalYearService::markExpiredAll()` to iterate all organizations and call `markExpired()` for each. Create it if it doesn't exist.

### Step 3.3 — Dashboard: Expired Year Banner

When the current org has a fiscal year in `expired` status and no year in `closed` status for that period, show a banner on the dashboard:
> "Your {year} fiscal year has ended. Close it to finalize your accounts and start the next year."

With action: "Close Year" button.

### Step 3.4 — Post-Closing: Prompt for Next Year

After `FiscalYearService::close()` succeeds, ensure the response (or a flash message) prompts the user to verify the next fiscal year's `Planned` status. If no planned year exists, auto-create one with the correct start date.

---

## Phase 4 — Paper Migration Support

### Step 4.1 — Opening Balances: Scenario Guide

**File:** `resources/js/Pages/Accounting/OpeningBalances.vue`

Add a collapsible "What do I need to enter?" guide that explains:
- For fresh starts: leave everything at zero
- For migrations: enter the closing balance sheet from the prior year (assets, liabilities, equity)
- Link to docs for more detail

### Step 4.2 — Historical Summary Entry

Allow users to record a prior-year P&L summary as a single journal entry tagged `historical_summary`. This entry is:
- Excluded from year-end closing calculations (already the case)
- Visible in the journal with a `[Historical]` badge
- Created via a dedicated UI in the Opening Balances page

---

## Key Files Reference

| File | Purpose |
|------|---------|
| `app/Domains/Organizations/Enums/OrganizationModule.php` | Module presets per business type |
| `app/Domains/Reporting/Controllers/DashboardController.php` | Dashboard Inertia controller |
| `app/Domains/Reporting/Services/DashboardService.php` | Dashboard metrics computation (cached 5 min) |
| `app/Domains/Organizations/Services/ChecklistService.php` | Onboarding checklist items |
| `app/Domains/Accounting/Services/FiscalYearService.php` | Fiscal year lifecycle; `markExpired()`, `close()` |
| `resources/js/Pages/Dashboard.vue` | Dashboard Vue page |
| `resources/js/Pages/Setup/Wizard.vue` | Onboarding wizard (4-step) |
| `resources/js/Pages/Accounting/Export.vue` | Year-end export page (HelpText + ZIP card) |
| `resources/js/Pages/Accounting/OpeningBalances.vue` | Opening balance entry form |
| `routes/console.php` | Scheduled commands |
| `lang/{en,fr,de,it}/app.php` | Translation files (all 4 must stay in sync) |
