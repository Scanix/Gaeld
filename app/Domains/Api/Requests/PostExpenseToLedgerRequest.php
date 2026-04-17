<?php

namespace App\Domains\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostExpenseToLedgerRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expense_account_code' => ['required', 'string', 'max:20'],
            'bank_account_code' => ['nullable', 'string', 'max:20'],
        ];
    }
}
