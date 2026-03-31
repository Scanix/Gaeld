<?php

namespace App\Domains\Organizations\Enums;

/** Organization membership roles. */
enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
    case Accountant = 'accountant';
    case Member = 'member';
    case Viewer = 'viewer';

    /**
     * @return Permission[]
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Owner => Permission::cases(),
            self::Admin => $this->adminPermissions(),
            self::Accountant => $this->accountantPermissions(),
            self::Member => $this->memberPermissions(),
            self::Viewer => $this->viewerPermissions(),
        };
    }

    /**
     * @return string[]
     */
    public function permissionValues(): array
    {
        return array_map(fn (Permission $p) => $p->value, $this->permissions());
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /**
     * @return Permission[]
     */
    private function adminPermissions(): array
    {
        return array_filter(
            Permission::cases(),
            fn (Permission $p) => $p !== Permission::OrganizationDelete,
        );
    }

    /**
     * @return Permission[]
     */
    private function memberPermissions(): array
    {
        return [
            // Accounting
            Permission::AccountingView,
            Permission::AccountingCreate,
            Permission::AccountingEdit,

            // Banking
            Permission::BankingView,
            Permission::BankingCreate,
            Permission::BankingEdit,
            Permission::BankingImport,
            Permission::BankingReconcile,

            // Contacts
            Permission::ContactsView,
            Permission::ContactsCreate,
            Permission::ContactsEdit,

            // Expenses
            Permission::ExpensesView,
            Permission::ExpensesCreate,
            Permission::ExpensesEdit,
            Permission::ExpensesApprove,

            // Invoicing
            Permission::InvoicingView,
            Permission::InvoicingCreate,
            Permission::InvoicingEdit,
            Permission::InvoicingFinalize,
            Permission::InvoicingRecordPayment,

            // Organization
            Permission::OrganizationView,
            Permission::OrganizationViewAuditLog,

            // Reporting
            Permission::ReportingView,

            // Payroll
            Permission::PayrollView,
            Permission::PayrollCreate,
            Permission::PayrollEdit,
        ];
    }

    /**
     * @return Permission[]
     */
    private function accountantPermissions(): array
    {
        return [
            // Accounting (full access including delete & close-year)
            Permission::AccountingView,
            Permission::AccountingCreate,
            Permission::AccountingEdit,
            Permission::AccountingDelete,
            Permission::AccountingCloseYear,

            // Banking (view + import/reconcile, no create/edit/delete)
            Permission::BankingView,
            Permission::BankingImport,
            Permission::BankingReconcile,

            // Contacts (view/create/edit, no delete)
            Permission::ContactsView,
            Permission::ContactsCreate,
            Permission::ContactsEdit,

            // Expenses (view/create/edit/approve, no delete)
            Permission::ExpensesView,
            Permission::ExpensesCreate,
            Permission::ExpensesEdit,
            Permission::ExpensesApprove,

            // Invoicing (view/create/edit/finalize/record-payment, no delete)
            Permission::InvoicingView,
            Permission::InvoicingCreate,
            Permission::InvoicingEdit,
            Permission::InvoicingFinalize,
            Permission::InvoicingRecordPayment,

            // Organization
            Permission::OrganizationView,
            Permission::OrganizationViewAuditLog,

            // Reporting
            Permission::ReportingView,

            // Payroll (view/create/edit, no delete)
            Permission::PayrollView,
            Permission::PayrollCreate,
            Permission::PayrollEdit,
        ];
    }

    /**
     * @return Permission[]
     */
    private function viewerPermissions(): array
    {
        return [
            Permission::AccountingView,
            Permission::BankingView,
            Permission::ContactsView,
            Permission::ExpensesView,
            Permission::InvoicingView,
            Permission::OrganizationView,
            Permission::ReportingView,
            Permission::PayrollView,
        ];
    }
}
