<?php

namespace App\Domains\Invoicing\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', \App\Domains\Invoicing\Models\Invoice::class);
    }

    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'currency' => 'string|size:3',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'customer_id' => [
                'required',
                Rule::exists('customers', 'id')->where('organization_id', $orgId),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'justificatif' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.document').'|max:'.config('uploads.max_size.document'),
        ];
    }
}
