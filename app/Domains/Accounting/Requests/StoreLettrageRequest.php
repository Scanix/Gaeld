<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreLettrageRequest extends FormRequest
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
            'account_id' => ['required', 'integer'],
            'line_ids' => ['required', 'array', 'min:2'],
            'line_ids.*' => ['required', 'integer'],
        ];
    }
}
