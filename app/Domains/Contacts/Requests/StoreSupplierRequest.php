<?php

namespace App\Domains\Contacts\Requests;

use App\Domains\Contacts\Validation\SupplierValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class StoreSupplierRequest extends FormRequest
{
    /** @return array<string, string|array<int, mixed>> */
    public function rules(): array
    {
        return SupplierValidationRules::store();
    }
}
