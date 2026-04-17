<?php

namespace App\Domains\Contacts\Requests;

use App\Domains\Contacts\Validation\CustomerValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerRequest extends FormRequest
{
    /** @return array<string, string> */
    public function rules(): array
    {
        return CustomerValidationRules::store();
    }
}
