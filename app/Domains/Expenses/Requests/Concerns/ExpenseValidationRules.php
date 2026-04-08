<?php

namespace App\Domains\Expenses\Requests\Concerns;

use App\Domains\Expenses\Enums\ExpenseType;
use Illuminate\Validation\Rule;

trait ExpenseValidationRules
{
    /** @return array<string, mixed> */
    protected function sharedRules(string $orgId): array
    {
        return [
            'category' => 'required|string|max:100',
            'description' => 'nullable|string',
            'amount' => 'required|numeric|min:0.01',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('suppliers', 'id')->where('organization_id', $orgId),
            ],
            'date' => 'required|date',
            'vendor' => 'nullable|string|max:255',
            'currency' => 'string|size:3',
            'type' => ['sometimes', Rule::enum(ExpenseType::class)],
            'receipt' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.receipt').'|max:'.config('uploads.max_size.receipt'),
            'expense_account_code' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'code')->where('organization_id', $orgId),
            ],
            'bank_account_code' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'code')->where('organization_id', $orgId),
            ],
        ];
    }
}
