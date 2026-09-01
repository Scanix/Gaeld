<?php

namespace App\Domains\Api\Requests;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreInvoiceApiRequest extends FormRequest
{
    /**
     * Defense-in-depth: enforce policy at the FormRequest layer in addition to the controller.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Invoice::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = app(CurrentOrganization::class);
        $orgId = $org->isBound() ? $org->id() : 0;

        return [
            'customer_id' => [
                'required',
                'uuid',
                Rule::exists('contacts', 'uuid')->where('organization_id', $orgId),
            ],
            'number' => 'nullable|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'string|size:3',
            'tax_treatment' => [
                'nullable',
                Rule::enum(InvoiceTaxTreatment::class),
                function (string $attribute, mixed $value, \Closure $fail) use ($orgId): void {
                    if ($value !== InvoiceTaxTreatment::ReverseCharge->value) {
                        return;
                    }

                    $customer = Contact::where('uuid', $this->input('customer_id'))
                        ->where('organization_id', $orgId)
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
            'lines' => 'required|array|min:1|max:500',
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
