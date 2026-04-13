<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReconcileExpenseRequest extends FormRequest
{
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
            'expense_account_code' => 'required|string|max:10',
        ];
    }
}
