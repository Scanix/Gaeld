<?php

namespace App\Domains\Api\Requests;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceApiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = app(CurrentOrganization::class);
        $orgId = $org->isBound() ? $org->id() : 0;

        return [
            'customer_id' => [
                'sometimes',
                'uuid',
                Rule::exists('contacts', 'uuid')->where('organization_id', $orgId),
            ],
            'number' => 'nullable|string|max:50',
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'sometimes|string|size:3',
            'tax_treatment' => [
                'sometimes',
                Rule::enum(InvoiceTaxTreatment::class),
                function (string $attribute, mixed $value, \Closure $fail) use ($orgId): void {
                    if ($value !== InvoiceTaxTreatment::ReverseCharge->value) {
                        return;
                    }

                    $customerId = $this->input('customer_id');
                    $invoice = $this->route('invoice');
                    $existingCustomerId = $invoice instanceof Invoice ? $invoice->customer_id : null;
                    $customer = Contact::query()
                        ->where('organization_id', $orgId)
                        ->when($customerId !== null, fn ($query) => $query->where('uuid', $customerId))
                        ->when($customerId === null, fn ($query) => $query->whereKey($existingCustomerId))
                        ->first();

                    if ($customer === null || ! InvoiceTaxTreatment::isEuCountry($customer->country)) {
                        $fail(__('app.invoice_reverse_charge_eu_customer_required'));
                    } elseif (! InvoiceTaxTreatment::hasValidEuVatNumber($customer->country, $customer->vat_number)) {
                        $fail(__('app.invoice_reverse_charge_vat_number_required'));
                    }
                },
            ],
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'sometimes|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate_id' => [
                'nullable',
                'uuid',
                Rule::exists('vat_rates', 'uuid')->where('organization_id', $orgId),
            ],
        ];
    }
}
