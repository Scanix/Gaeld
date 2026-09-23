<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Services\ExpenseAccountResolver;
use App\Domains\Expenses\Services\ExpenseAmountNormalizer;
use App\Domains\Expenses\Services\ExpenseCategoryResolver;

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
        private ?ExpenseCategoryResolver $categoryResolver = null,
        private ?ExpenseAccountResolver $accountResolver = null,
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
        $category = ($this->categoryResolver ?? app(ExpenseCategoryResolver::class))->resolve(
            $data->organizationId,
            $data->expenseCategoryId,
            $data->category,
        );
        $account = ($this->accountResolver ?? app(ExpenseAccountResolver::class))->resolve(
            $data->organizationId,
            $data->expenseAccountId,
            $data->expenseAccountCode,
            $category,
        );
        unset($expenseData['amount_basis']);
        $expenseData['amount'] = $breakdown->netAmount;
        $expenseData['vat_amount'] = $breakdown->vatAmount;
        $expenseData['expense_category_id'] = $category?->id;
        $expenseData['expense_account_id'] = $account?->id;
        if ($account !== null && blank($expenseData['expense_account_code'] ?? null)) {
            $expenseData['expense_account_code'] = $account->code;
        }

        return Expense::create([
            ...$expenseData,
            'status' => ExpenseStatus::Pending,
        ]);
    }
}
