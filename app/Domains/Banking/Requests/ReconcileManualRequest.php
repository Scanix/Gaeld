<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReconcileManualRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'contra_account_code' => 'required|string|max:10',
        ];
    }
}
