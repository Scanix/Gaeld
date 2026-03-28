<?php

namespace App\Domains\Organizations\Enums;

enum Role: string
{
    case Owner = 'owner';
    case Admin = 'admin';
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
