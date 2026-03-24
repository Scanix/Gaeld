<?php

namespace App\Domains\Organizations\Enums;

enum Permission: string
{
    // Accounting
    case AccountingView = 'accounting.view';
    case AccountingCreate = 'accounting.create';
    case AccountingEdit = 'accounting.edit';
    case AccountingDelete = 'accounting.delete';
    case AccountingCloseYear = 'accounting.close-year';

    // Banking
    case BankingView = 'banking.view';
    case BankingCreate = 'banking.create';
    case BankingEdit = 'banking.edit';
    case BankingDelete = 'banking.delete';
    case BankingImport = 'banking.import';
    case BankingReconcile = 'banking.reconcile';

    // Contacts
    case ContactsView = 'contacts.view';
    case ContactsCreate = 'contacts.create';
    case ContactsEdit = 'contacts.edit';
    case ContactsDelete = 'contacts.delete';

    // Expenses
    case ExpensesView = 'expenses.view';
    case ExpensesCreate = 'expenses.create';
    case ExpensesEdit = 'expenses.edit';
    case ExpensesDelete = 'expenses.delete';
    case ExpensesApprove = 'expenses.approve';

    // Invoicing
    case InvoicingView = 'invoicing.view';
    case InvoicingCreate = 'invoicing.create';
    case InvoicingEdit = 'invoicing.edit';
    case InvoicingDelete = 'invoicing.delete';
    case InvoicingFinalize = 'invoicing.finalize';
    case InvoicingRecordPayment = 'invoicing.record-payment';

    // Organization
    case OrganizationView = 'organization.view';
    case OrganizationEdit = 'organization.edit';
    case OrganizationManageUsers = 'organization.manage-users';
    case OrganizationDelete = 'organization.delete';

    // Reporting
    case ReportingView = 'reporting.view';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
