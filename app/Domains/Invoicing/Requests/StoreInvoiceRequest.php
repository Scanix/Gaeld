<?php

namespace App\Domains\Invoicing\Requests;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Requests\Concerns\InvoiceValidationRules;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    use InvoiceValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('create', Invoice::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return $this->sharedRules(app(CurrentOrganization::class)->id());
    }
}
