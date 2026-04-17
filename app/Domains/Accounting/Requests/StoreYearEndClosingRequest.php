<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreYearEndClosingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'closing_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:50'],
            'result_account_code' => ['required', 'string', 'max:20'],
        ];
    }
}
