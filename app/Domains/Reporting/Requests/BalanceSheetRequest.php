<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class BalanceSheetRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'as_of_date' => ['nullable', 'date'],
        ];
    }
}
