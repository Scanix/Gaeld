<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ProfitAndLossRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'compare_from' => ['nullable', 'date'],
            'compare_to' => ['nullable', 'date', 'required_with:compare_from', 'after_or_equal:compare_from'],
        ];
    }
}
