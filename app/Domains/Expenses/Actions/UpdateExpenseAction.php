<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;

/**
 * Updates an editable expense (only pending expenses can be modified).
 *
 * VAT amount is always recomputed server-side from the effective VAT rate;
 * the client-provided value is intentionally ignored to prevent financial fraud.
 */
class UpdateExpenseAction
{
    public function execute(Expense $expense, UpdateExpenseData $data): Expense
    {
        if (! $expense->status->isEditable()) {
            throw new InvalidExpenseStateException('Posted expenses cannot be modified.');
        }

        // Resolve the effective vat_rate_id before updating so VAT is computed correctly.
        $vatRateId = $data->vatRateId ?? $expense->vat_rate_id;

        // Compute VAT server-side before the update; never trust $data->vatAmount.
        $vatAmount = $this->computeVatAmount($vatRateId, $data->amount);

        $expense->update([
            'category' => $data->category,
            'description' => $data->description ?? $expense->description,
            'amount' => $data->amount,
            'vat_rate_id' => $vatRateId,
            'vat_amount' => $vatAmount,
            'date' => $data->date,
            'vendor' => $data->vendor ?? $expense->vendor,
            'supplier_id' => $data->supplierId ?? $expense->supplier_id,
            'receipt_path' => $data->receiptPath ?? $expense->receipt_path,
            'currency' => $data->currency ?? $expense->currency,
            'payment_method' => $data->paymentMethod ?? $expense->payment_method,
            'expense_account_code' => $data->expenseAccountCode ?? $expense->expense_account_code,
            'bank_account_code' => $data->bankAccountCode ?? $expense->bank_account_code,
        ]);

        return $expense->fresh();
    }

    /**
     * Compute VAT amount from the rate record: amount × (rate / 100), BCMath precision.
     */
    private function computeVatAmount(mixed $vatRateId, string $amount): string
    {
        if (! $vatRateId) {
            return '0';
        }

        /** @var VatRate|null $vatRate */
        $vatRate = VatRate::find($vatRateId);

        if (! $vatRate) {
            return '0';
        }

        /** @var numeric-string $amount */
        return bcmul($amount, bcdiv((string) $vatRate->rate, '100', 6), 2);
    }
}
