<?php

namespace App\Domains\Expenses\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\RecurrenceFrequency;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RecurringExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Expense::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'category' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'vat_amount' => ['nullable', 'numeric', 'min:0'],
            'vendor' => ['nullable', 'string', 'max:255'],
            'currency' => ['nullable', 'string', 'size:3'],
            'payment_method' => ['nullable', Rule::in(['cash', 'card', 'bank_transfer', 'other'])],
            'frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'next_due_date' => ['required', 'date'],
            'end_date' => ['nullable', 'date', 'after:next_due_date'],
            'is_active' => ['sometimes', 'boolean'],
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'supplier_id' => [
                'nullable',
                Rule::exists('contacts', 'id')->where('organization_id', $orgId)->whereNull('deleted_at'),
            ],
            'expense_account_code' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $orgId)
                    ->where('type', AccountType::Expense->value),
            ],
            'bank_account_code' => [
                'nullable',
                'string',
                Rule::exists('accounts', 'code')->where('organization_id', $orgId),
            ],
        ];
    }
}
