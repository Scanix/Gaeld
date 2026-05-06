<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;

/**
 * Creates a new expense record in pending status.
 *
 * VAT amount is always computed server-side from the VAT rate; the client-provided
 * value is intentionally ignored to prevent financial fraud.
 */
class CreateExpenseAction
{
    public function execute(CreateExpenseData $data): Expense
    {
        // Compute VAT server-side before creating; never trust $data->vatAmount.
        $vatAmount = $this->computeVatAmount($data->vatRateId, $data->amount);

        return Expense::create([
            ...$data->toArray(),
            'status' => ExpenseStatus::Pending,
            'vat_amount' => $vatAmount,
        ]);
    }

    /**
     * Compute VAT amount from the rate record: amount × (rate / 100), BCMath precision.
     */
    private function computeVatAmount(?string $vatRateId, string $amount): string
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
