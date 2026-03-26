<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfitAndLossRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
        ];
    }
}
