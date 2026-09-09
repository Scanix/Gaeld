<?php

namespace App\Domains\Banking\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Banking\Models\BankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileExpenseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaction = $this->route('transaction');

        return $transaction instanceof BankTransaction
            && $this->user()?->can('update', $transaction->bankAccount) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $bankAccount = $this->route('transaction')->bankAccount;

        return [
            'expense_id' => [
                'required',
                'uuid',
                Rule::exists('expenses', 'id')->where('organization_id', $bankAccount->organization_id),
            ],
            'expense_account_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $bankAccount->organization_id)
                    ->where('type', AccountType::Expense->value)
                    ->where('is_active', true),
            ],
        ];
    }
}
