<?php

namespace App\Domains\Expenses\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseCategoryRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:100'],
            'default_expense_account_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('type', AccountType::Expense->value)
                    ->where('is_active', true),
            ],
        ];
    }
}
