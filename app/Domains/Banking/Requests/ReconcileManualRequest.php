<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileManualRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'contra_account_code' => 'required|string|max:10',
        ];
    }
}
