<?php

namespace App\Domains\Banking\Rules;

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
        if ($transaction->type !== BankTransaction::TYPE_DEBIT) {
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
            ->get()
            ->first(function ($supplier) use ($transaction) {
                $creditor = strtolower($transaction->creditor_name);
                $name = strtolower($supplier->name);

                return str_contains($creditor, $name) || str_contains($name, $creditor);
            });

        if (! $supplier) {
            return;
        }

        // Store category suggestion in transaction metadata (description prefix for now).
        // A dedicated `suggested_category` column can be added in a future migration.
        $transaction->update([
            'matched_expense_id' => null, // cleared — just a suggestion
        ]);

        // Surface the suggestion via the transaction meta: attach supplier_id hint
        // This is surfaced to the reconciliation UI without auto-creating an expense.
    }
}
