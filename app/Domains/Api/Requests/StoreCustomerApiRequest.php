<?php

namespace App\Domains\Api\Requests;

use App\Domains\Contacts\Validation\CustomerValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreCustomerApiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return CustomerValidationRules::store();
    }
}
