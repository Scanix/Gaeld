<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseAmountNormalizer;

/**
 * Creates a new expense record in pending status.
 *
 * VAT amount is always computed server-side from the VAT rate; the client-provided
 * value is intentionally ignored to prevent financial fraud.
 */
class CreateExpenseAction
{
    public function __construct(
        private ?ExpenseAmountNormalizer $amountNormalizer = null,
    ) {}

    public function execute(CreateExpenseData $data): Expense
    {
        $breakdown = ($this->amountNormalizer ?? app(ExpenseAmountNormalizer::class))->normalize(
            $data->organizationId,
            $data->amount,
            $data->vatRateId,
            $data->amountBasis,
        );

        $expenseData = $data->toArray();
        unset($expenseData['amount_basis']);
        $expenseData['amount'] = $breakdown->netAmount;
        $expenseData['vat_amount'] = $breakdown->vatAmount;

        return Expense::create([
            ...$expenseData,
            'status' => ExpenseStatus::Pending,
        ]);
    }
}
