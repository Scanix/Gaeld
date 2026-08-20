# Per-Page Validation Checklist

Use this as a quick-scan checklist during live browser audits. Mark ✅ / ❌ / ⚠️.

---

## Every Page (run on each route visited)

- [ ] Page loads without JS console errors
- [ ] Page title is set and descriptive (browser tab)
- [ ] No broken/missing images
- [ ] No layout overflow or horizontal scroll (desktop)
- [ ] Primary action button is clearly visible
- [ ] Back/breadcrumb navigation is present and works
- [ ] User identity (name / avatar) visible in nav
- [ ] Logout accessible from current page

---

## Auth Pages

### `/login`
- [ ] Email field has `type="email"` and proper label
- [ ] Password field masked with show/hide toggle
- [ ] "Remember me" checkbox present
- [ ] Submit triggers loading state (button disabled + spinner)
- [ ] Wrong credentials shows specific, user-friendly error
- [ ] Enter key submits the form
- [ ] Link to `/forgot-password` present
- [ ] Link to `/register` present
- [ ] No PII leaked in error messages

### `/register`
- [ ] All required fields labelled with asterisk or visible convention
- [ ] Password strength indicator or requirements shown
- [ ] Password confirm field present
- [ ] Terms of service / privacy policy link present
- [ ] Duplicate email error is informative
- [ ] Redirect after success goes to onboarding/dashboard (not login again)

### `/forgot-password`
- [ ] Single email field, clear instruction text
- [ ] Success state shown after submission (not same form again)
- [ ] No email enumeration: same message for known/unknown emails
- [ ] Return to login link present

### `/two-factor-challenge`
- [ ] OTP input: numeric, 6-digit, auto-submit on fill
- [ ] Passkey option clearly labelled and visible
- [ ] Recovery code option present
- [ ] Error on invalid code is non-generic
- [ ] Resend/re-trigger option available

### `/onboarding`
- [ ] Step indicator shows progress
- [ ] "Skip for now" available on optional steps
- [ ] Going back doesn't lose previously entered data
- [ ] Final step redirects to dashboard immediately

---

## Dashboard (`/`)

- [ ] Loads with real data, not placeholder strings
- [ ] KPI cards display correct currency/number formatting
- [ ] Empty state: meaningful text + CTA when no data exists
- [ ] Recent activity feed (if present) shows correct timestamps
- [ ] Quick-action buttons work
- [ ] Charts/graphs render (not blank canvas)

---

## Contacts

### List view
- [ ] Search filters results in real time or on submit
- [ ] Pagination / load-more works
- [ ] Clicking a row navigates to detail

### Detail view
- [ ] All fields populated with correct data
- [ ] Edit button opens form pre-filled
- [ ] Delete requires confirmation modal
- [ ] Related invoices/expenses shown

---

## Invoices

### List
- [ ] Status badges colour-coded (draft/sent/paid/overdue)
- [ ] Filters work (status, date range, contact)
- [ ] Sort by date/amount works

### Create / Edit
- [ ] Contact picker autocompletes
- [ ] Adding a line item updates totals immediately
- [ ] Removing last line item is handled gracefully (not blank/NaN total)
- [ ] VAT/tax field calculation is correct
- [ ] "Save as draft" vs "Send" are distinct actions
- [ ] Validation prevents sending invoice with no line items

### Send flow
- [ ] Email preview shows correct recipient and content
- [ ] Confirmation step before sending
- [ ] Success toast/notification after send
- [ ] Status changes to "Sent" in list

---

## Expenses

- [ ] Receipt upload: drag-and-drop works, file size limit communicated
- [ ] Category dropdown populated
- [ ] Amount + currency + date fields required
- [ ] Submitted expense shows in list immediately
- [ ] Approval state transitions are visible

---

## Banking

- [ ] Account balance displayed correctly (not null/NaN)
- [ ] Transaction list loads and paginate
- [ ] Import/sync action triggers visible loading state
- [ ] Reconciled vs unreconciled transactions distinguishable

---

## Reports

- [ ] Date range picker is usable (calendar navigation, manual input)
- [ ] Generate report does not produce a blank page
- [ ] CSV export downloads a non-empty file
- [ ] PDF export renders layout correctly (check preview)

---

## Settings

### Profile
- [ ] Pre-filled with current user data
- [ ] Avatar upload works
- [ ] Saving shows success feedback

### Organisation
- [ ] Logo upload works
- [ ] Currency / timezone fields present

### Team / Invitations
- [ ] Invite form validates email
- [ ] Pending invitations list shown
- [ ] Revoke invitation works

### Danger Zone
- [ ] Delete account behind typed confirmation
- [ ] Action is irreversible — warning copy is explicit

---

## Mobile (375 px viewport)

- [ ] Hamburger / mobile menu toggle visible
- [ ] All navigation items accessible via mobile menu
- [ ] No text truncated to unreadable length
- [ ] Modals are full-screen or properly constrained
- [ ] Date pickers and dropdowns usable on touch
- [ ] No fixed elements overlap content

---

## Accessibility Spot Checks

- [ ] Run keyboard-only navigation: can you reach every CTA?
- [ ] Check with DevTools colour contrast analyser on primary text
- [ ] Verify all `<img>` have `alt` text (use DevTools → Elements)
- [ ] Verify modals set `role="dialog"` and `aria-labelledby`
- [ ] Form error messages associated with inputs via `aria-describedby`
