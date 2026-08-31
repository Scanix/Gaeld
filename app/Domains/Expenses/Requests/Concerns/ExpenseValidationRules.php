<?php

namespace App\Domains\Expenses\Requests\Concerns;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Expenses\Enums\ExpenseType;
use App\Domains\Expenses\Validation\ExpenseSharedValidationRules;
use App\Domains\Organizations\Enums\Permission;
use Illuminate\Validation\Rule;

trait ExpenseValidationRules
{
    /** @return array<string, mixed> */
    protected function sharedRules(string $orgId): array
    {
        $selfService = $this->user()->hasPermissionTo(Permission::ExpensesViewOwn)
            && ! $this->user()->hasPermissionTo(Permission::ExpensesView);

        return array_merge(ExpenseSharedValidationRules::store(), [
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'supplier_id' => [
                $selfService ? 'prohibited' : 'nullable',
                Rule::exists('contacts', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'type' => ['sometimes', Rule::enum(ExpenseType::class)],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'other'])],
            'receipt' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.receipt').'|max:'.config('uploads.max_size.receipt'),
            'receipt_path' => ['nullable', 'string', 'starts_with:receipts/'],
            'scan_id' => ['nullable', 'string', 'uuid'],
            'expense_account_code' => [
                $selfService ? 'prohibited' : 'nullable',
                'string',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $orgId)
                    ->where('type', AccountType::Expense->value),
            ],
            'bank_account_code' => [
                $selfService ? 'prohibited' : 'nullable',
                'string',
                Rule::exists('accounts', 'code')->where('organization_id', $orgId),
            ],
        ]);
    }
}
