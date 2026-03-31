<?php

namespace App\Domains\Contacts\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return [
            'type' => 'nullable|string|in:organization,individual',
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'default_expense_category' => 'nullable|string|max:100',
            'currency' => 'nullable|string|size:3',
            'iban' => 'nullable|string|max:34',
            'internal_notes' => 'nullable|string',
            'notes' => 'nullable|string|max:2000',
        ];
    }
}
