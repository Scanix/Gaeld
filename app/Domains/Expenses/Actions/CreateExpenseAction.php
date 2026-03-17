<?php

namespace App\Domains\Expenses\Actions;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;

class CreateExpenseAction
{
    public function execute(array $data): Expense
    {
        return Expense::create([
            'organization_id' => $data['organization_id'],
            'vat_rate_id' => $data['vat_rate_id'] ?? null,
            'category' => $data['category'],
            'description' => $data['description'] ?? null,
            'amount' => $data['amount'],
            'vat_amount' => $data['vat_amount'] ?? 0,
            'date' => $data['date'],
            'vendor' => $data['vendor'] ?? null,
            'receipt_path' => $data['receipt_path'] ?? null,
            'status' => ExpenseStatus::Pending->value,
            'currency' => $data['currency'] ?? 'CHF',
        ]);
    }
}
