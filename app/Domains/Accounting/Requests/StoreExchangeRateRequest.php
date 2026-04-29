<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreExchangeRateRequest extends FormRequest
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
            'currency_from' => ['required', 'string', 'size:3', 'alpha', 'different:currency_to'],
            'currency_to' => ['required', 'string', 'size:3', 'alpha'],
            'rate' => ['required', 'numeric', 'gt:0'],
            'date' => ['required', 'date'],
        ];
    }
}
