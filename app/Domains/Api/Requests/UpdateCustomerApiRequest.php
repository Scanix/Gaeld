<?php

namespace App\Domains\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerApiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'sometimes|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:50',
            'address' => 'nullable|string|max:500',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'nullable|string|size:3',
            'payment_terms' => 'nullable|string|max:255',
            'internal_notes' => 'nullable|string',
        ];
    }
}
