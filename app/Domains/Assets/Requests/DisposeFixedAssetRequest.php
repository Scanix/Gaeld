<?php

namespace App\Domains\Assets\Requests;

use Illuminate\Foundation\Http\FormRequest;

class DisposeFixedAssetRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'disposal_amount' => ['required', 'numeric', 'min:0'],
            'disposal_date' => ['required', 'date'],
        ];
    }
}
