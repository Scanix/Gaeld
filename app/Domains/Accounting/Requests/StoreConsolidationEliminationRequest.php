<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreConsolidationEliminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'account_debit_id' => ['required', 'integer'],
            'account_credit_id' => ['required', 'integer', 'different:account_debit_id'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
