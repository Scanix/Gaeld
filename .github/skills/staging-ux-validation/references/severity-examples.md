# Severity Examples

Concrete examples to calibrate issue severity ratings.

---

## 🔴 CRITICAL — Blocks usage, data loss risk, or security issue

- Submitting the invoice create form throws a 500 error and the invoice is not saved
- Clicking "Delete organisation" deletes immediately without any confirmation
- The login page redirects authenticated users to `/` but the route requires `?org=X` and crashes with a 404
- API endpoint returns another user's data (IDOR)
- Password reset token is reusable after use
- A JavaScript error crashes the entire Vue app and shows a blank white screen
- File upload accepts `.php` files (RCE risk)

---

## 🟠 HIGH — Significant UX friction or business logic error

- Invoice totals do not update when a line item quantity is changed
- The "Send invoice" button is active even when the invoice has no line items (no server-side validation either)
- Mobile nav menu opens but items are not clickable (z-index overlap)
- Pagination "Next" button navigates to page 2 but the URL doesn't update (breaks browser back button)
- Form submits successfully but shows no feedback — user re-submits and creates a duplicate
- "Forgot password" flow sends the same email twice when the user clicks submit quickly
- Date picker defaults to year 1970 on Firefox

---

## 🟡 MEDIUM — Noticeable but has a workaround

- Table column widths collapse awkwardly at 1024 px but the data is still readable
- "Save" button says "Save" on create but "Save" on edit — should say "Update" for clarity
- Toast notification for "Contact saved" auto-dismisses in 1 second — too fast to read
- Password field has no show/hide toggle — user must retype if they suspect a typo
- The "Generate PDF" button shows no loading state — user doesn't know if it's working
- Breadcrumb shows `Contacts > undefined` when the contact has no name yet

---

## 🟢 LOW — Polish / minor inconsistency

- Button border-radius is `4px` on the login page and `6px` everywhere else
- "Organisation" is spelled "Organization" on one settings screen
- Icon for "payroll" is a briefcase in the sidebar and a dollar sign in the mobile menu
- Footer copyright year is 2024
- Empty contact list says "No results found" but the search field is blank (should say "No contacts yet")
- Dropdown menu closes on scroll (minor interaction annoyance)

---

## Notes on Scoring

- If two CRITICAL issues exist in one area → score that area **≤ 3/10**
- If zero CRITICAL issues but 3+ HIGH issues → score **4–5/10**
- If no CRITICAL or HIGH issues, only MEDIUM → score **6–7/10**
- Clean page with only LOW issues → score **8–9/10**
- No issues at all → score **10/10** (be sceptical if you reach this)
