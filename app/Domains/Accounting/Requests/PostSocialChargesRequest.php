<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PostSocialChargesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'gt:0', 'max:99999999.99'],
            'description' => ['required', 'string', 'max:255'],
            'date' => ['nullable', 'date'],
        ];
    }
}
