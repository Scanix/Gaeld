<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Contacts\Queries\ContactQuery;

/**
 * EE Rule: When a debit transaction creditor matches a known Supplier,
 * suggest the supplier's default expense category.
 *
 * Confidence: 90 (name match — very likely correct, but human review advised).
 */
class SupplierCategoryRule extends BaseRule
{
    public function name(): string
    {
        return 'Supplier Category Suggestion';
    }

    public function confidence(): int
    {
        return 90;
    }

    public function matches(BankTransaction $transaction): bool
    {
        if ($transaction->type !== BankTransactionType::Debit) {
            return false;
        }

        if (! $transaction->creditor_name) {
            return false;
        }

        $orgId = $transaction->bankAccount->organization_id;

        return ContactQuery::hasMatchingSupplier($orgId, $transaction->creditor_name);
    }

    public function apply(BankTransaction $transaction): void
    {
        if (! $transaction->creditor_name) {
            return;
        }

        $orgId = $transaction->bankAccount->organization_id;

        $supplier = ContactQuery::findByCreditorName($orgId, $transaction->creditor_name);

        if (! $supplier) {
            return;
        }

        $transaction->update([
            'suggested_expense_category' => $supplier->default_expense_category,
        ]);
    }
}
