<?php

namespace App\Domains\Api\Requests;

use App\Domains\Contacts\Validation\SupplierValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierApiRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return SupplierValidationRules::store();
    }
}
