<?php

namespace App\Domains\Organizations\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SetupWizardRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'user_name' => 'required|string|max:255',
            'user_email' => 'required|email|unique:users,email',
            'user_password' => 'required|string|min:8|confirmed',
            'org_name' => 'required|string|max:255',
            'org_legal_name' => 'nullable|string|max:255',
            'org_address' => 'nullable|string',
            'org_city' => 'nullable|string|max:100',
            'org_postal_code' => 'nullable|string|max:10',
            'org_canton' => 'nullable|string|size:2',
            'org_vat_number' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'locale' => 'required|string|in:en,fr,de,it',
        ];
    }
}
