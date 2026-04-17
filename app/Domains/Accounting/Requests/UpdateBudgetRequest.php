<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateBudgetRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
