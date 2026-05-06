<?php

namespace App\Domains\Contacts\Requests;

use App\Domains\Contacts\Validation\ContactValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreContactRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ContactValidationRules::store();
    }
}
