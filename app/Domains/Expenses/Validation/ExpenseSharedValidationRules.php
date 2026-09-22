<?php

namespace App\Domains\Expenses\Validation;

use App\Domains\Expenses\Enums\ExpenseAmountBasis;
use Illuminate\Validation\Rule;

class ExpenseSharedValidationRules
{
    /** @return array<string, mixed> */
    public static function store(): array
    {
        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_basis' => ['sometimes', Rule::enum(ExpenseAmountBasis::class)],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /** @return array<string, mixed> */
    public static function update(): array
    {
        return [
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'amount_basis' => ['sometimes', Rule::enum(ExpenseAmountBasis::class)],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
