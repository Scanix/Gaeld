<?php

namespace App\Domains\Invoicing\Requests;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Invoicing\Requests\Concerns\InvoiceValidationRules;
use Illuminate\Foundation\Http\FormRequest;

class UpdateInvoiceRequest extends FormRequest
{
    use InvoiceValidationRules;

    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('invoice'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Invoice $invoice */
        $invoice = $this->route('invoice');

        return $this->sharedRules($invoice->organization_id, $invoice->id);
    }
}
