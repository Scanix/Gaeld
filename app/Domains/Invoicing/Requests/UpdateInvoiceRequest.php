<?php

namespace App\Domains\Invoicing\Requests;

use App\Domains\Invoicing\Requests\Concerns\InvoiceValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use InvoiceValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    public function rules(): array
    {
        return $this->sharedRules($this->route('invoice')->organization_id);
    }
}
