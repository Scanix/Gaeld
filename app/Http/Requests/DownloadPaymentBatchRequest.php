<?php

namespace App\Http\Requests;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class DownloadPaymentBatchRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', BankAccount::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'bank_account_id' => [
                'required',
                'integer',
                Rule::exists('bank_accounts', 'id')
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true)
                    ->whereNotNull('iban'),
            ],
            'expense_ids' => ['required', 'array', 'min:1', 'max:500'],
            'expense_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('expenses', 'id')
                    ->where('organization_id', $organizationId)
                    ->whereIn('status', [ExpenseStatus::Pending->value, ExpenseStatus::Approved->value])
                    ->whereNull('journal_entry_id'),
            ],
            'execution_date' => ['nullable', 'date'],
        ];
    }
}
