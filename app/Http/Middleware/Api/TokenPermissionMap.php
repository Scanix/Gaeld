<?php

namespace App\Http\Middleware\Api;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Api\Models\Webhook;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;

/**
 * Maps model class + policy ability to the required Spatie permission.
 *
 * Used by EnsureApiOrganization to evaluate organization token access
 * without going through full policy org-membership checks.
 */
final class TokenPermissionMap
{
    /**
     * @return array<string, array<string, Permission>>
     */
    public static function get(): array
    {
        return [
            Contact::class => [
                'viewAny' => Permission::ContactsView,
                'view' => Permission::ContactsView,
                'create' => Permission::ContactsCreate,
                'update' => Permission::ContactsEdit,
                'delete' => Permission::ContactsDelete,
            ],
            Invoice::class => [
                'viewAny' => Permission::InvoicingView,
                'view' => Permission::InvoicingView,
                'create' => Permission::InvoicingCreate,
                'update' => Permission::InvoicingEdit,
                'delete' => Permission::InvoicingDelete,
                'finalize' => Permission::InvoicingFinalize,
                'recordPayment' => Permission::InvoicingRecordPayment,
                'send' => Permission::InvoicingEdit,
                'cancel' => Permission::InvoicingEdit,
                'creditNote' => Permission::InvoicingCreate,
            ],
            Expense::class => [
                'viewAny' => Permission::ExpensesView,
                'view' => Permission::ExpensesView,
                'create' => Permission::ExpensesCreate,
                'update' => Permission::ExpensesEdit,
                'delete' => Permission::ExpensesDelete,
                'approve' => Permission::ExpensesApprove,
            ],
            Account::class => [
                'viewAny' => Permission::AccountingView,
                'view' => Permission::AccountingView,
            ],
            JournalEntry::class => [
                'viewAny' => Permission::AccountingView,
                'view' => Permission::AccountingView,
                'create' => Permission::AccountingCreate,
                'post' => Permission::AccountingEdit,
                'reverse' => Permission::AccountingEdit,
                'update' => Permission::AccountingEdit,
                'delete' => Permission::AccountingDelete,
            ],
            BankAccount::class => [
                'viewAny' => Permission::BankingView,
                'view' => Permission::BankingView,
                'import' => Permission::BankingImport,
            ],
            Organization::class => [
                'view' => Permission::OrganizationView,
                'update' => Permission::OrganizationEdit,
                'delete' => Permission::OrganizationDelete,
                'manageUsers' => Permission::OrganizationManageUsers,
                'viewAuditLog' => Permission::OrganizationViewAuditLog,
            ],
            Webhook::class => [
                'viewAny' => Permission::OrganizationEdit,
                'view' => Permission::OrganizationEdit,
                'create' => Permission::OrganizationEdit,
                'update' => Permission::OrganizationEdit,
                'delete' => Permission::OrganizationEdit,
                'regenerateSecret' => Permission::OrganizationEdit,
            ],
        ];
    }
}
