<?php

namespace App\Domains\Banking\Requests;

use App\Domains\Banking\Models\BankTransaction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileManualRequest extends FormRequest
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
        $transaction = $this->route('transaction');
        $organizationId = $transaction instanceof BankTransaction
            ? $transaction->bankAccount->organization_id
            : '';

        return [
            'contra_account_code' => [
                'required',
                'string',
                'max:10',
                Rule::exists('accounts', 'code')
                    ->where('organization_id', $organizationId)
                    ->where('is_active', true),
            ],
        ];
    }
}
