---
name: two-year-e2e-test
description: >
  End-to-end manual test playbook that drives Gäld through the full lifecycle
  of a Swiss SME (1 chief + 2 employees) over two fiscal years plus an
  opening-balance migration from a paper-based fiduciary. Run it in SaaS
  mode (FEATURE_SAAS=true) via the browser tools to validate signup,
  onboarding, invoicing, expenses, banking, payroll, VAT, year-end closing,
  archives, and the second-year reopen flow. The run MUST end by writing a
  consolidated test report to `docs/qa/two-year-e2e-<YYYYMMDD>.md` covering
  every phase, every defect, and screenshots. Activate when the user asks
  for a full functional regression test, a SaaS smoke test, a year-end
  rehearsal, or any "two year" / "long year" / "paper migration" walkthrough.
---

# Gäld — Two-Year SaaS End-to-End Test Playbook

## Purpose

Exercise **every user-facing feature** end-to-end in **SaaS mode** through
the browser tools, simulating a realistic Swiss SME that:

1. Migrated from a paper fiduciary mid-2024 (a **long fiscal year** of
   ~18 months: 2024-01-01 → 2025-06-30).
2. Ran a full normal year on Gäld: 2025-07-01 → 2026-06-30.
3. Started a third year and reopened the first one for adjustments.

This playbook is a **test script**, not a feature spec — do not change
production code while running it unless the user explicitly asks you to
fix a bug surfaced by the test. Record every defect as you go.

---

## Test Fixture — "Helvetia Atelier Sàrl"

| Field             | Value                                         |
|-------------------|-----------------------------------------------|
| Legal form        | Sàrl (GmbH)                                   |
| Canton            | VD (Vaud)                                     |
| Address           | Rue du Lac 12, 1003 Lausanne                  |
| VAT no.           | CHE-123.456.789 MWST                          |
| VAT regime        | Effective method, quarterly                   |
| Currency          | CHF                                           |
| Locale            | fr                                            |
| Fiscal year start | 1 July (after the long migration year)        |
| Plan (SaaS)       | Business / Standard (must include payroll)    |

**People**

| Role     | Name              | Email                           | Gross/month |
|----------|-------------------|---------------------------------|-------------|
| Chief    | Claire Moreau     | claire@helvetia-atelier.test    | 9 500 CHF   |
| Employee | Lionel Harber     | lionel@helvetia-atelier.test    | 6 200 CHF   |
| Employee | Sofia Bernasconi  | sofia@helvetia-atelier.test     | 5 800 CHF   |

**Customers** (create at least these)

- ACME Industries SA (Lausanne, VAT CHE-987.654.321) — recurring
- Studio Fenix GmbH (Zürich) — one-off
- Mairie de Pully — public sector, no VAT recovery on their side

**Suppliers** (create at least these)

- Swisscom (telecom)
- CFF / SBB (travel)
- Migros Pro (office supplies)
- Romande Energie (utilities)
- Helvetia Assurances (insurance)
- AVS/AHV caisse cantonale VD (social charges)

---

## Pre-flight (do once, before any browser work)

1. Confirm SaaS mode is on:
   ```bash
   vendor/bin/sail artisan config:show features.saas
   ```
   If `false`, set `FEATURE_SAAS=true` in `.env` and run
   `vendor/bin/sail artisan config:clear`.
2. Make sure the app is reachable: `vendor/bin/sail open` (port 8080 by
   default — confirm via `get-absolute-url`).
3. Open Mailpit at `http://localhost:8025` in a second browser tab — every
   signup, invoice email, payroll slip, and notification must be inspected
   there.
4. Decide test mode:
   - **Clean run** (recommended): wipe DB → `vendor/bin/sail artisan migrate:fresh --seed`.
   - **Append run**: keep existing data, just verify Helvetia Atelier flows.
5. Record findings in a fresh file: `docs/qa/two-year-e2e-<YYYYMMDD>.md`
   (one heading per phase, with screenshots taken via `screenshot_page`).

---

## Anti-divergence Rules

1. **Do not modify production code** unless fixing a confirmed bug, and only
   after asking the user.
2. **Never bypass workflow warnings** by running artisan commands — click
   through the UI like a real user. CLI use is reserved for time travel
   (see Phase 6) and seeding the paper-migration journal.
3. **Time travel via `Carbon::setTestNow()`** in a tinker session is
   acceptable for dates that would otherwise require waiting (VAT
   deadlines, fiscal year cutoff). Reset with `Carbon::setTestNow(null)`
   after each phase.
4. **Capture every email** in Mailpit; missing notifications are bugs.
5. **One persona per tab** when testing multi-user flows — use separate
   browser pages for the chief vs. employees so sessions don't collide.
6. **Stop and ask** before destructive actions (delete org, drop archive,
   force-close fiscal year early).
7. **Do not relax the 23-month long-year cap** (`FiscalYearService::MAX_DURATION_MONTHS`).
   Helvetia Atelier's migration year is 18 months — within the cap.
8. Use **`historical_summary` journal entries** for the paper opening
   balance — do NOT post fake individual invoices for the paper period.

---

## Phase 0 — SaaS Signup & Onboarding (P1 → P3 path)

**Goal:** Verify the public marketing → signup → plan selection → org
creation → checklist flow.

1. Open `/` (logged out). Confirm the marketing page renders and the CTA
   points to `/signup` (not `/setup`, which is CE-only).
2. Click "Sign up", choose the **Business / Standard** plan (must include
   payroll module).
3. Fill the signup form as Claire Moreau. Submit.
4. Inspect Mailpit: welcome email + email verification (if required by the
   active plan).
5. Complete email verification by clicking the link in Mailpit.
6. Land on the org creation step — fill in the Helvetia Atelier fixture
   above. **Set fiscal year start to 1 January** for now (we will create
   the long year explicitly in Phase 1).
7. Verify the redirect lands on `/dashboard` with the onboarding checklist
   visible (see `dashboard-archives-ux` skill).
8. Walk through the checklist top-to-bottom, capturing screenshots:
   - Configure VAT (effective, quarterly).
   - Add a bank account (CH QR-IBAN — use any valid checksum).
   - Upload company logo (PNG ≤ 1 MB).
   - Invite Lionel and Sofia (Mailpit must show invite emails; accept both
     in separate browser tabs).
9. Switch language to fr / de / it / en at least once and confirm no
   missing translation keys (look for raw dotted keys in the UI).

**Expected defects to watch for:** unfiltered dashboard widget links,
missing translations, checklist items not marked complete, plan/module
mismatch on payroll page.

---

## Phase 1 — Paper Migration & Long Fiscal Year (P2)

**Goal:** Bring in the fiduciary's paper books as an opening balance and
configure the 18-month migration year **2024-01-01 → 2025-06-30**.

1. Settings → Fiscal Years → "Create long fiscal year".
   - Start: 2024-01-01.
   - End: 2025-06-30 (18 months — within the 23-month cap).
   - Confirm UI shows the long-year explanation (see
     `onboarding-improvements` skill, Step 4.x).
2. Settings → Accounting → "Import opening balance from paper".
   - Effective date: 2024-01-01.
   - Enter ~15 lines covering: cash, bank, AR, AP, inventory, fixed
     assets, VAT due/recoverable, AVS due, equity, retained earnings.
   - Total debits must equal total credits — UI must block submit if not.
   - Confirm the resulting journal entry has type `historical_summary`
     (see archives page or DB via `database-query`).
3. Verify these `historical_summary` lines are **excluded from year-end
   closing** (you'll re-check this in Phase 6).
4. Phase 1 paper period — **do not enter individual transactions** for
   Jan 2024 → June 2024. Optionally add a second `historical_summary`
   snapshot at 2024-06-30 for mid-year P&L reconciliation if the fiduciary
   provided one.
5. From **2024-07-01 onward**, switch behaviour: post real transactions
   for the remaining 12 months of the long year. Spread across the year:
   - 8–12 customer invoices/month (mix of all 3 customers, mix of 8.1%
     and 2.6% VAT, at least one 0% export to Studio Fenix).
   - 15–25 expense receipts/month (uploaded as PDF/JPG from
     `tests/fixtures/` or generated).
   - 3 payroll runs/month × 3 people × 12 months (see Phase 3).
   - 4 VAT quarter closings (Q3-24, Q4-24, Q1-25, Q2-25 — see Phase 4).
6. Time-travel each posting using `Carbon::setTestNow()` in tinker so
   `created_at` / journal dates land in the right month. Reset after
   each batch.

---

## Phase 2 — Daily Operations (run every simulated month)

For each simulated month, exercise the full operational loop. Repeat this
phase for the 12 active months of the long year, then the 12 months of
year 2, then 1–2 months of year 3.

### 2.1 Invoicing
- Create invoices via the QR-bill flow; verify PDF preview (page 1 letter,
  page 2 QR slip — matches the recent Swiss PDF rework).
- Send at least 5 invoices/month by email; confirm Mailpit delivery and
  recipient layout (sender top-left, customer top-right, dashed logo
  placeholder if none).
- Mark a portion paid (manual reconciliation), leave some open, let at
  least one go overdue → trigger reminder workflow.

### 2.2 Expenses
- Upload receipts via drag-and-drop and via mobile-style upload.
- Test OCR/auto-fill if available; correct mis-parses.
- Assign to suppliers and accounts; verify VAT recovery posts to the
  correct account.

### 2.3 Banking
- Import a CAMT.053 statement each month (use `tests/fixtures/camt`
  samples or generate one).
- Reconcile against invoices and expenses; leave 1–2 unmatched lines to
  test the unmatched-items widget.
- If `bank_sync` (EE) is enabled in this plan, also test the live sync
  path; otherwise skip.

### 2.4 Contacts
- Edit a customer mid-year (address change) and verify historical
  invoices still render the old address (immutability).

### 2.5 Reports (spot-check monthly)
- P&L, Balance Sheet, Trial Balance, Journal, Cash Flow, AR/AP aging.
- Verify CHF formatting, Swiss date format (d.m.Y), page footers, and
  that the new shared `_styles` / `_header` partials render across all 7
  PDF templates.

---

## Phase 3 — Payroll (run monthly for Claire, Lionel, Sofia)

1. Settings → Payroll → enable the module if not already on (verify the
   plan permits it; downgrade test if time allows).
2. Create the 3 employees with the gross salaries from the fixture.
   Configure AVS/AI/APG, AC, LAA, LPP, source-tax (none for residents in
   this scenario), 13th salary on Claire only.
3. Run monthly payroll for each simulated month. Verify:
   - Net salary calculation matches Swiss 2024 / 2025 scales.
   - Salary slip PDF uses the Swiss letter layout (SN 010130) — sender
     bar, recipient block at window-envelope position, place + date.
   - Salary slip emails go out via Mailpit (one per employee per month).
4. Generate the **year-end salary certificate** (Lohnausweis /
   certificat de salaire) for each employee at the close of each fiscal
   year (Phase 6). Verify the form is the official 2024/2025 template.
5. Test edge cases: one mid-year hire (e.g., Sofia starts 2024-09-01),
   one unpaid leave month, one expense reimbursement on top of salary.

---

## Phase 4 — VAT Quarterly Returns

For each of these quarters, walk through the full VAT return flow:

| Quarter | Period                  | Due (approx) |
|---------|-------------------------|--------------|
| Q3-24   | 2024-07-01 → 2024-09-30 | 2024-11-30   |
| Q4-24   | 2024-10-01 → 2024-12-31 | 2025-02-28   |
| Q1-25   | 2025-01-01 → 2025-03-31 | 2025-05-31   |
| Q2-25   | 2025-04-01 → 2025-06-30 | 2025-08-31   |
| Q3-25 … through Q2-26 (year-2 quarters)                |

For each:
1. Reports → VAT → generate the period return.
2. Verify reverse-charge handling on the Studio Fenix export (Zurich is
   domestic — substitute a real EU customer if you want to test export).
3. Lock / file the period; confirm the locked period cannot be modified.
4. Post the VAT payable settlement journal; reconcile against the bank.

---

## Phase 5 — Multi-User & Permissions

1. Log in as Lionel (employee role). Verify he can:
   - See his own salary slips.
   - Submit his own expense reports.
   - **Not** see other employees' payroll, full P&L, or org settings.
2. Log in as Sofia and repeat. Confirm spatie-permission roles match
   what `laravel-permission-development` skill prescribes.
3. As Claire (chief), invite a fiduciary user (P4) with read-only access
   to accounting and exports. Confirm they cannot post journal entries.
4. Test the org switcher if Claire is also a member of another test org.

---

## Phase 6 — Year-End Closing for the Long Year (2024-01-01 → 2025-06-30)

**Goal:** Run the full year-end wizard for the migration year.

1. Pre-checks (Reports → Year-End):
   - All VAT quarters in the year are locked.
   - Bank reconciled to 2025-06-30.
   - AR/AP aging printed and signed off.
   - Payroll closed for all 12 active months + AVS final declaration sent.
2. Year-End Wizard (per `dashboard-archives-ux` skill, Phase 5):
   - Step 1: Verify trial balance.
   - Step 2: Post depreciation / accruals / inventory adjustments.
   - Step 3: Review draft P&L and Balance Sheet.
   - Step 4: Confirm `historical_summary` entries are excluded from P&L
     but present in opening Balance Sheet.
   - Step 5: Generate fiduciary export ZIP (the export must work even for
     freelancer plans — see Anti-divergence rule in
     `onboarding-improvements`). Inspect contents: PDF reports + CSV
     journal + receipts archive.
   - Step 6: Close the year.
3. After closing:
   - Confirm all journal entries, invoices, and expenses for the year
     are **locked** (`archived_at` set AND no edit/delete in UI — per
     `dashboard-archives-ux` Phase 4).
   - Confirm Legal Archives page lists the year with its ZIP for the
     Swiss CO 10-year retention requirement.
4. Generate Lohnausweis 2024 and 2025 (partial) for each employee.

---

## Phase 7 — Second Fiscal Year (2025-07-01 → 2026-06-30)

1. Confirm the new fiscal year was auto-opened after Phase 6 close, with
   opening balances rolled from the closing BS.
2. Repeat Phase 2 (operations) and Phase 3 (payroll) for all 12 months.
3. Repeat Phase 4 (VAT quarters Q3-25 → Q2-26).
4. Mid-year, test the **fiscal year change request** flow — request a
   shift to a calendar year for year 3. The system should warn (not
   block) and queue the change for the next year-end.
5. Run year-end closing again (Phase 6 repeated for 2025-07-01 →
   2026-06-30). Confirm the wizard is faster the second time (gating
   steps already familiar; no opening-balance step).

---

## Phase 8 — Reopen & Adjustment Workflow

1. Start year 3 (2026-07-01 → …). Post a handful of operations.
2. Discover a "fiduciary correction" needed in year 1 (the long year).
   Trigger the reopen flow:
   - Confirm the user is warned that the archive ZIP will be regenerated.
   - Confirm reopen requires the chief role; Lionel/Sofia must be denied.
3. Post the adjustment journal in year 1, then re-close.
4. Verify the regenerated archive ZIP has a new hash and supersedes the
   old one (old version kept for audit, per CO 10-year rule).

---

## Phase 9 — Plan, Billing & Tenant Lifecycle (SaaS-specific)

1. Settings → Subscription → downgrade from Business to a plan **without
   payroll**. Confirm:
   - Existing payroll data is preserved and read-only.
   - New payroll runs are blocked with a clear upgrade CTA.
2. Upgrade back to Business; confirm payroll resumes.
3. Test plan-level limits if any (max employees, max invoices/month).
4. Cancel subscription (test mode). Confirm grace-period behaviour and
   final export reminder email.
5. Resubscribe; confirm data resumes intact.

---

## Phase 10 — Final Regression Sweep

Before closing the test session:

1. Run `vendor/bin/sail artisan test --compact` — no failures.
2. Run `vendor/bin/sail bin pint --test --format agent` — clean.
3. Run `vendor/bin/sail bin phpstan analyse` — no new errors above
   baseline.
4. Browser sweep:
   - Every left-nav item loads without JS console errors
     (`browser-logs`).
   - Every PDF export downloads and opens.
   - Locale switcher works on every page.
   - Dark mode (if available) renders cleanly.
5. Mailpit sweep: count emails per category, confirm none failed
   delivery.

---

## Phase 11 — Final Report (mandatory)

The playbook is **not complete** until the consolidated report is written.
Do not stop at Phase 10. Even if earlier phases were skipped, blocked, or
partially run, you MUST produce the report file describing what was done,
what was skipped (with reason), and every defect observed.

### Steps

1. Resolve the report path:
   ```bash
   echo "docs/qa/two-year-e2e-$(date +%Y%m%d).md"
   ```
   If a file already exists for today, append a `-2`, `-3`, … suffix —
   never overwrite a previous run.
2. Create the file using the template below.
3. Embed every screenshot taken during the run under
   `docs/qa/assets/two-year-e2e-<YYYYMMDD>/` and link them with relative
   markdown image syntax.
4. For each P1/P2 defect, open a GitHub issue (ask the user first if the
   repo has issue templates) and link the issue number in the report.
5. Print the absolute report path back to the user as the final chat
   message, along with the pass/fail summary line.

### Report template

Append to `docs/qa/two-year-e2e-<YYYYMMDD>.md`:

```markdown
# Two-Year E2E Test — <date>

**Tester:** Copilot agent
**Mode:** SaaS (FEATURE_SAAS=true)
**Build:** <git rev-parse HEAD>

## Summary
- Phases passed: X / 11
- Phases skipped: <list with reason>
- Defects (P1/P2/P3): a / b / c
- Verdict: ✅ Ship / ⚠️ Ship with caveats / ❌ Block

## Environment
- App URL: <get-absolute-url output>
- Git HEAD: <sha>
- Test data scope: clean / append
- Time-travel windows used: <list>

## Phase 0 — Signup & Onboarding
- Status: ✅ / ⚠️ / ❌
- Walkthrough: <bullet points of what was clicked>
- Emails captured (Mailpit): <count + categories>
- Defects:
  - [P2] <one-line description> — repro: <steps> — screenshot:
    `assets/two-year-e2e-<YYYYMMDD>/phase0-<n>.png`
- Notes: …

## Phase 1 — Paper Migration & Long Fiscal Year
(repeat the same block per phase, through Phase 10)

## Defects Index
| ID  | Severity | Phase | Title                       | Issue        |
|-----|----------|-------|-----------------------------|--------------|
| D1  | P1       | 6     | Year-end wizard crashes on … | #1234        |

## Artifacts
- Screenshots: `docs/qa/assets/two-year-e2e-<YYYYMMDD>/`
- Generated PDFs reviewed: <list of 9 PDFs from generate-preview-pdfs.php>
- Archive ZIPs produced: <paths or storage keys>

## Follow-ups
- <action item> — owner: <person>
```

Open one GitHub issue per P1/P2 defect, link to the screenshot, and tag
the relevant domain owner.
