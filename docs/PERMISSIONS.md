# Permissions & Roles

> **Source of truth**: [`Permission.php`](app/Domains/Organizations/Enums/Permission.php) and [`Role.php`](app/Domains/Organizations/Enums/Role.php)  
> **Package**: [spatie/laravel-permission](https://github.com/spatie/laravel-permission) v7 with teams mode (`organization_id`)  
> **Last updated**: 2026-03-30

---

## Roles

| Role | Description | Permissions |
|------|-------------|:-----------:|
| **Owner** | Full unrestricted access. Can delete the organization. | 36 / 36 |
| **Admin** | Everything except deleting the organization. | 35 / 36 |
| **Accountant** | Full accounting power (incl. delete accounts & close year). View/create/edit across other modules. No module deletes except accounting. No org management. | 26 / 36 |
| **Member** | Day-to-day usage: view/create/edit. Cannot delete records, manage users, or close fiscal years. | 22 / 36 |
| **Viewer** | Read-only access across all modules. | 8 / 36 |

---

## Permission Matrix

Legend: **●** = granted, **○** = denied

| Permission | Owner | Admin | Accountant | Member | Viewer |
|------------|:-----:|:-----:|:----------:|:------:|:------:|
| **Accounting** | | | | | |
| `accounting.view` | ● | ● | ● | ● | ● |
| `accounting.create` | ● | ● | ● | ● | ○ |
| `accounting.edit` | ● | ● | ● | ● | ○ |
| `accounting.delete` | ● | ● | ● | ○ | ○ |
| `accounting.close-year` | ● | ● | ● | ○ | ○ |
| **Banking** | | | | | |
| `banking.view` | ● | ● | ● | ● | ● |
| `banking.create` | ● | ● | ○ | ● | ○ |
| `banking.edit` | ● | ● | ○ | ● | ○ |
| `banking.delete` | ● | ● | ○ | ○ | ○ |
| `banking.import` | ● | ● | ● | ● | ○ |
| `banking.reconcile` | ● | ● | ● | ● | ○ |
| **Contacts** | | | | | |
| `contacts.view` | ● | ● | ● | ● | ● |
| `contacts.create` | ● | ● | ● | ● | ○ |
| `contacts.edit` | ● | ● | ● | ● | ○ |
| `contacts.delete` | ● | ● | ○ | ○ | ○ |
| **Expenses** | | | | | |
| `expenses.view` | ● | ● | ● | ● | ● |
| `expenses.create` | ● | ● | ● | ● | ○ |
| `expenses.edit` | ● | ● | ● | ● | ○ |
| `expenses.delete` | ● | ● | ○ | ○ | ○ |
| `expenses.approve` | ● | ● | ● | ● | ○ |
| **Invoicing** | | | | | |
| `invoicing.view` | ● | ● | ● | ● | ● |
| `invoicing.create` | ● | ● | ● | ● | ○ |
| `invoicing.edit` | ● | ● | ● | ● | ○ |
| `invoicing.delete` | ● | ● | ○ | ○ | ○ |
| `invoicing.finalize` | ● | ● | ● | ● | ○ |
| `invoicing.record-payment` | ● | ● | ● | ● | ○ |
| **Organization** | | | | | |
| `organization.view` | ● | ● | ● | ● | ● |
| `organization.edit` | ● | ● | ○ | ○ | ○ |
| `organization.manage-users` | ● | ● | ○ | ○ | ○ |
| `organization.delete` | ● | ○ | ○ | ○ | ○ |
| `organization.view-audit-log` | ● | ● | ● | ● | ○ |
| **Reporting** | | | | | |
| `reporting.view` | ● | ● | ● | ● | ● |
| **Payroll** | | | | | |
| `payroll.view` | ● | ● | ● | ● | ● |
| `payroll.create` | ● | ● | ● | ● | ○ |
| `payroll.edit` | ● | ● | ● | ● | ○ |
| `payroll.delete` | ● | ● | ○ | ○ | ○ |

---

## Permission Descriptions

### Accounting

| Permission | Description |
|------------|-------------|
| `accounting.view` | View chart of accounts, trial balance, journal entries, reports |
| `accounting.create` | Create accounts, journal entries |
| `accounting.edit` | Edit accounts, post/reverse journal entries |
| `accounting.delete` | Delete accounts (only if no transactions exist) |
| `accounting.close-year` | Execute fiscal year-end closing |

### Banking

| Permission | Description |
|------------|-------------|
| `banking.view` | View bank accounts and transactions |
| `banking.create` | Create bank accounts |
| `banking.edit` | Edit bank account details |
| `banking.delete` | Delete bank accounts (only if no transactions exist) |
| `banking.import` | Import bank statements (CAMT, CSV) |
| `banking.reconcile` | Reconcile bank transactions with journal entries |

### Contacts

| Permission | Description |
|------------|-------------|
| `contacts.view` | View customers and suppliers |
| `contacts.create` | Create customers and suppliers |
| `contacts.edit` | Edit customer/supplier details |
| `contacts.delete` | Delete customers/suppliers |

### Expenses

| Permission | Description |
|------------|-------------|
| `expenses.view` | View expenses |
| `expenses.create` | Create expenses, upload receipts |
| `expenses.edit` | Edit expense details |
| `expenses.delete` | Delete expenses |
| `expenses.approve` | Approve or reject submitted expenses |

### Invoicing

| Permission | Description |
|------------|-------------|
| `invoicing.view` | View invoices and recurring invoices |
| `invoicing.create` | Create invoices |
| `invoicing.edit` | Edit draft invoices |
| `invoicing.delete` | Delete invoices |
| `invoicing.finalize` | Finalize a draft invoice (locks for editing) |
| `invoicing.record-payment` | Record a payment against an invoice |

### Organization

| Permission | Description |
|------------|-------------|
| `organization.view` | View organization details and settings |
| `organization.edit` | Edit organization settings, logo, fiscal year |
| `organization.manage-users` | Invite, remove, and change roles of members |
| `organization.delete` | Permanently delete the organization and all data |
| `organization.view-audit-log` | View the activity / audit log |

### Reporting

| Permission | Description |
|------------|-------------|
| `reporting.view` | Access all reports (P&L, balance sheet, cash flow, VAT, aging) |

### Payroll

| Permission | Description |
|------------|-------------|
| `payroll.view` | View employees and salary slips |
| `payroll.create` | Create employees, generate payroll runs |
| `payroll.edit` | Edit employee details, post salary slips |
| `payroll.delete` | Delete employees |

---

## Architecture

### How Permissions Are Enforced

```
Request → Middleware (org resolution) → Controller → $this->authorize() → Policy → hasPermissionTo()
```

1. **Middleware** ([`EnsureHasOrganization`](app/Http/Middleware/EnsureHasOrganization.php)) resolves the current organization and calls `setPermissionsTeamId($org->id)`.
2. **Controllers** call `$this->authorize('ability', Model::class)` to trigger Laravel's Gate/Policy system.
3. **Policies** (in `app/Domains/*/Policies/`) check two things:
   - Organization membership (`belongsToOrganization()` or `hasCurrentOrganization()`)
   - Permission (`$user->hasPermissionTo(Permission::Xyz)`)
4. **Frontend** receives all permission names via Inertia shared props (`page.props.auth.permissions`). The [`usePermissions()`](resources/js/lib/usePermissions.js) composable provides `can('permission.name')`, `hasRole('role')`, and `hasAnyRole(...)`.

### API Tokens

API tokens use [`TokenPermissionMap`](app/Http/Middleware/Api/TokenPermissionMap.php) to map policy abilities to Spatie permissions:

- **Personal tokens**: Check both token abilities AND policies (dual verification).
- **Organization tokens**: Bypass policies, only check token abilities.

### Key Files

| File | Purpose |
|------|---------|
| [`Permission.php`](app/Domains/Organizations/Enums/Permission.php) | All permission definitions (enum) |
| [`Role.php`](app/Domains/Organizations/Enums/Role.php) | Role definitions and permission assignments (enum) |
| [`RolesAndPermissionsSeeder.php`](database/seeders/RolesAndPermissionsSeeder.php) | Idempotent seeder (iterates enums dynamically) |
| [`BasePolicy.php`](app/Support/Policies/BasePolicy.php) | Shared `belongsToOrganization()` / `hasCurrentOrganization()` |
| [`TokenPermissionMap.php`](app/Http/Middleware/Api/TokenPermissionMap.php) | Maps policy abilities → Spatie permissions for API tokens |
| [`usePermissions.js`](resources/js/lib/usePermissions.js) | Frontend composable: `can()`, `hasRole()`, `hasAnyRole()` |
| [`Sidebar.vue`](resources/js/Components/Sidebar.vue) | Sidebar navigation gated by `can()` |

### Adding a New Permission

1. Add a case to `Permission.php`.
2. Add it to the relevant roles in `Role.php`.
3. Create a migration that re-syncs permissions (see `2026_03_30_000000_add_accountant_role_and_audit_log_permission.php` for pattern).
4. Use it in the relevant Policy.
5. Gate sidebar/UI if needed.
6. Add to `TokenPermissionMap` if exposed via API.
7. Add tests in `tests/Security/Authorization/`.

### Adding a New Role

1. Add a case to `Role.php`.
2. Create a `private function xyzPermissions(): array` returning the permission set.
3. Add the match arm in `permissions()`.
4. Create a migration that re-syncs (same pattern as above).
5. Add tests in `tests/Security/Authorization/`.
