---
name: staging-ux-validation
description: "Full UX, UI, and business logic validation of the staging environment using the browser tool. Use when asked to: validate staging, audit the staging site, review staging.home.nectoria.com, run a UX audit, run a UI audit, check staging quality, validate business flows, or produce a staging report. Navigates every key area of the app, captures screenshots, and produces a critical written report with severity ratings."
argument-hint: "Optional: specific area to focus on (e.g. 'onboarding', 'invoices', 'auth')"
---

# Staging UX / UI / Business Validation

## Purpose

Perform a rigorous, opinionated end-to-end quality audit of **https://staging.home.nectoria.com** and produce a written report. Be genuinely critical — surface real problems, not just cosmetic observations.

---

## Step 0 — Tool Setup

Load the browser tools before starting:

```
tool_search: "open browser page screenshot click navigate"
```

Always use `get-absolute-url` (Laravel Boost MCP) to confirm the correct base URL before navigating.

---

## Step 1 — Environment Check

1. Open `https://staging.home.nectoria.com` and take a screenshot.
2. Confirm the page loads (no 502/503/empty screen).
3. Check the browser console via `browser-logs` for JavaScript errors, failed network requests, and warnings.
4. Note the current git branch / build info if visible in the UI (e.g. footer or meta tags).

---

## Step 2 — Authentication Flows

Navigate and critically evaluate each auth screen.

| Page | URL | What to check |
|------|-----|---------------|
| Login | `/login` | Form labels, placeholder text, field validation, error messages, "Remember me", keyboard navigation, submit feedback |
| Register | `/register` | Field order, password rules visibility, email validation, terms/privacy link presence |
| Forgot password | `/forgot-password` | Clear instructions, success state feedback, back-to-login link |
| 2FA challenge | `/two-factor-challenge` | Code input UX, passkey option visibility, resend option |
| Email verify | `/email/verify` | Messaging clarity, resend button, what happens after login if unverified |
| Onboarding | `/onboarding` | Step progression, required field labelling, skip/later option |
| Setup wizard | `/setup` | First-time setup clarity, error recovery |

**Critical checks per auth page:**
- Is every error message human-readable and actionable?
- Are passwords masked with a show/hide toggle?
- Is there throttle feedback shown to the user (e.g. "Too many attempts, wait 60 s")?
- Does tab order follow logical reading order?
- Is focus management correct after form submission?

---

## Step 3 — Core Application Pages

After logging in (or using a demo/test account if credentials are provided), audit every major section. Take screenshots of each.

### 3.1 Dashboard (`/`)
- Is the most important data visible without scrolling (above the fold)?
- Are empty states meaningful (not just blank)? Do they provide a CTA?
- Are number formatting, currency symbols, and date formats consistent?
- Is the loading/skeleton state implemented?

### 3.2 Contacts (`/contacts`)
- List view: column density, sort/filter UX, pagination or infinite scroll
- Create/edit: form layout, required vs optional fields, inline validation
- Detail view: related data hierarchy, action button placement

### 3.3 Invoices (`/invoices`)
- List: status badges (draft / sent / paid / overdue) — are they colour-coded and accessible?
- Create: line item UX, tax fields, totals calculation in real-time
- PDF/print preview: layout fidelity
- Send flow: email preview, confirmation step

### 3.4 Expenses (`/expenses`)
- Receipt upload UX
- Category assignment
- Approval workflow steps (if present)

### 3.5 Banking (`/banking`)
- Account connection states
- Transaction list: date, description, amount formatting
- Reconciliation UX clarity

### 3.6 Accounting (`/accounting`)
- Chart of accounts hierarchy
- Journal entry form
- Report generation triggers

### 3.7 Payroll (`/payroll`)
- Run payroll flow steps
- Employee list completeness
- Pay slip preview

### 3.8 Reports (`/reports`)
- Date range picker UX
- Export (CSV/PDF) — does it actually trigger?
- Chart/graph readability

### 3.9 Settings
- Profile, organisation, team members, billing, API tokens (if enabled)
- Danger zone actions (delete org, revoke token) — are they behind a confirmation?

---

## Step 4 — UX & Design Quality Audit

For every page visited, score and note issues under these dimensions:

### 4.1 Visual Consistency
- [ ] Typography: consistent font sizes, weights, line heights across pages
- [ ] Colour: primary/secondary/danger/success used consistently; check against Tailwind config
- [ ] Spacing: consistent padding/margin tokens (not ad-hoc px values)
- [ ] Component parity: same element (button, badge, input) looks identical across pages

### 4.2 Interaction Design
- [ ] All interactive elements have hover/focus/active states
- [ ] Loading indicators shown for async operations (spinners, skeletons, progress bars)
- [ ] Destructive actions require confirmation
- [ ] Undo / recovery path exists for irreversible actions
- [ ] Toast/notification messages have correct severity icons and auto-dismiss timing

### 4.3 Responsive Design
- Take screenshots at mobile (375 px), tablet (768 px), and desktop (1440 px) widths using:
  ```js
  await page.setViewportSize({ width: 375, height: 812 });
  ```
- [ ] No horizontal scroll at 375 px
- [ ] Navigation collapses to mobile menu correctly
- [ ] Tables degrade to card/accordion layout on mobile
- [ ] Modals are scrollable on mobile

### 4.4 Accessibility
- [ ] All form inputs have associated `<label>` elements (inspect DOM)
- [ ] Icon-only buttons have `aria-label`
- [ ] Colour contrast meets WCAG AA (4.5:1 for normal text, 3:1 for large text)
- [ ] Keyboard focus visible on all interactive elements
- [ ] Modals trap focus and restore it on close

### 4.5 Performance Perception
- [ ] Time-to-interactive on initial page load (estimate from network tab)
- [ ] Lazy loading for heavy lists
- [ ] No layout shift (CLS) during load

---

## Step 5 — Business Logic Validation

Test these critical flows end-to-end:

1. **Registration → Onboarding → Dashboard**: Can a new user reach a usable state in under 3 minutes?
2. **Create Invoice → Send → Mark Paid**: Full invoice lifecycle
3. **Create Contact → Attach to Invoice**: Relationship integrity
4. **Add Expense → Categorise → Approve**: Approval flow
5. **Settings → Invite Team Member → Accept Invitation**: Multi-user flow
6. **Forgot Password → Reset → Login**: Recovery flow

For each flow, note:
- Broken steps (errors, missing data, wrong redirects)
- Missing confirmation feedback
- Business rule violations (e.g. invoice with no line items should not be submittable)
- Confusing or ambiguous UI copy

---

## Step 6 — Console & Network Errors

After completing the tour:

1. Pull all logs from `browser-logs`.
2. Identify and classify:
   - **JS errors**: component crashes, undefined property access, Vue warnings
   - **HTTP 4xx/5xx**: API calls failing silently
   - **Missing assets**: 404 on fonts/images/scripts
   - **Deprecation warnings**: outdated Vue or browser APIs

---

## Step 7 — Report

Write the report in this exact structure:

---

### STAGING VALIDATION REPORT
**Date**: `<date>`
**URL**: `https://staging.home.nectoria.com`
**Branch/Version**: `<if visible>`

---

#### Executive Summary
2–4 sentences. Overall quality verdict. Is it shippable? What is the single most critical blocker?

---

#### Severity Scale
| Level | Meaning |
|-------|---------|
| 🔴 CRITICAL | Blocks usage, data loss risk, or security issue — must fix before release |
| 🟠 HIGH | Significant UX friction or business logic error — fix this sprint |
| 🟡 MEDIUM | Noticeable problem but has a workaround — fix next sprint |
| 🟢 LOW | Polish / minor inconsistency — fix when time allows |
| ✅ OK | No issue found |

---

#### Issues Found

For each issue, use:

```
[SEVERITY] Short Title
Area: <page or section>
Description: What is wrong, with specific evidence (e.g. selector, URL, screenshot reference)
Impact: Who is affected and how
Recommendation: Specific fix
```

---

#### Scores by Category

| Category | Score /10 | Key Finding |
|----------|-----------|-------------|
| Auth & Onboarding | | |
| Core Features | | |
| Visual Consistency | | |
| Interaction Design | | |
| Responsive Design | | |
| Accessibility | | |
| Business Logic | | |
| Console Health | | |
| **Overall** | | |

---

#### Quick Wins (< 1 day effort)
Bulleted list of LOW/MEDIUM issues that are fast to fix and high visual impact.

#### Must Fix Before Next Release
Bulleted list of CRITICAL and HIGH issues with a one-line fix.

---

## Behaviour Rules

- **Be blunt.** A score of 7/10 for a broken flow is dishonest. Score what you see.
- **Always take a screenshot** before evaluating a page. Do not evaluate from memory.
- **If you cannot log in**, document the credential blocker in the report and continue with all public-facing pages.
- **Do not skip sections** just because they seem similar. Each domain (invoices vs expenses) has unique business rules.
- **Reference specific elements**: "the 'Save' button in the invoice edit form" not "a button".
- Use `browser-logs` after every major flow, not just at the end.

---

## See Also

- [./references/checklist.md](./references/checklist.md) — Printable per-page checklist
- [./references/severity-examples.md](./references/severity-examples.md) — Concrete examples per severity level
- [./assets/report-template.md](./assets/report-template.md) — Empty report template to copy-paste
