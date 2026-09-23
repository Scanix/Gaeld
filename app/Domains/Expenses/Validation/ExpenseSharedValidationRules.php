<?php

namespace App\Domains\Expenses\Validation;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Expenses\Enums\ExpenseAmountBasis;
use Illuminate\Validation\Rule;

class ExpenseSharedValidationRules
{
    /** @return array<string, mixed> */
    public static function store(?string $organizationId = null): array
    {
        $categoryRule = Rule::exists('expense_categories', 'id');
        $accountRule = Rule::exists('accounts', 'id')
            ->where('type', AccountType::Expense->value)
            ->where('is_active', true);

        if ($organizationId !== null) {
            $categoryRule->where('organization_id', $organizationId);
            $accountRule->where('organization_id', $organizationId);
        }

        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'amount_basis' => ['sometimes', Rule::enum(ExpenseAmountBasis::class)],
            'expense_category_id' => [
                'nullable',
                'uuid',
                $categoryRule->where('is_active', true),
            ],
            'expense_account_id' => [
                'nullable',
                'integer',
                $accountRule,
            ],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }

    /** @return array<string, mixed> */
    public static function update(?string $organizationId = null): array
    {
        $categoryRule = Rule::exists('expense_categories', 'id');
        $accountRule = Rule::exists('accounts', 'id')
            ->where('type', AccountType::Expense->value)
            ->where('is_active', true);

        if ($organizationId !== null) {
            $categoryRule->where('organization_id', $organizationId);
            $accountRule->where('organization_id', $organizationId);
        }

        return [
            'category' => ['sometimes', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'amount' => ['sometimes', 'numeric', 'min:0.01'],
            'amount_basis' => ['sometimes', Rule::enum(ExpenseAmountBasis::class)],
            'expense_category_id' => [
                'nullable',
                'uuid',
                $categoryRule->where('is_active', true),
            ],
            'expense_account_id' => [
                'nullable',
                'integer',
                $accountRule,
            ],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'date' => ['sometimes', 'date'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
        ];
    }
}
