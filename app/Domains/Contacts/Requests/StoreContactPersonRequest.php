<?php

namespace App\Domains\Contacts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreContactPersonRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:255'],
            'is_primary' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }
}
