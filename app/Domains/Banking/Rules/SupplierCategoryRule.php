<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Contacts\Models\Supplier;

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

        return Supplier::where('organization_id', $orgId)
            ->whereNotNull('default_expense_category')
            ->where('name', 'ilike', '%' . $transaction->creditor_name . '%')
            ->exists();
    }

    public function apply(BankTransaction $transaction): void
    {
        if (! $transaction->creditor_name) {
            return;
        }

        $orgId = $transaction->bankAccount->organization_id;

        $supplier = Supplier::where('organization_id', $orgId)
            ->whereNotNull('default_expense_category')
            ->where('name', 'ilike', '%' . $transaction->creditor_name . '%')
            ->first();

        if (! $supplier) {
            return;
        }

        $transaction->update([
            'suggested_expense_category' => $supplier->default_expense_category,
        ]);
    }
}
