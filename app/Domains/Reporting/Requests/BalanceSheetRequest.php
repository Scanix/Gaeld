<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'as_of_date' => ['nullable', 'date'],
        ];
    }
}
