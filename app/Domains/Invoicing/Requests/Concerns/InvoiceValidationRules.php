<?php

namespace App\Domains\Invoicing\Requests\Concerns;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceLineType;
use App\Domains\Invoicing\Enums\InvoiceTaxTreatment;
use App\Support\Money;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Validation\Rule;

trait InvoiceValidationRules
{
    /** @return array<string, mixed> */
    protected function sharedRules(string $orgId, ?string $ignoreInvoiceId = null): array
    {
        $finalize = $this->boolean('finalize');

        return [
            'number' => [
                'required',
                'string',
                'max:50',
                Rule::unique('invoices', 'number')
                    ->where('organization_id', $orgId)
                    ->when($ignoreInvoiceId !== null, fn ($rule) => $rule->ignore($ignoreInvoiceId)),
            ],
            'issue_date' => 'required|date',
            'due_date' => [$finalize ? 'required' : 'nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => 'string|size:3',
            'tax_treatment' => [
                'nullable',
                Rule::enum(InvoiceTaxTreatment::class),
                function (string $attribute, mixed $value, \Closure $fail) use ($orgId): void {
                    if ($value !== InvoiceTaxTreatment::ReverseCharge->value) {
                        return;
                    }

                    $customer = Contact::withoutGlobalScope('organization')
                        ->where('organization_id', $orgId)
                        ->whereKey($this->input('customer_id'))
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
            'lines' => 'required|array|min:1',
            'lines.*.type' => ['nullable', Rule::enum(InvoiceLineType::class)],
            'lines.*.discount_type' => ['nullable', 'in:flat,percentage'],
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required_unless:lines.*.type,text|numeric|min:0.01',
            'lines.*.unit_price' => 'required_unless:lines.*.type,text|numeric',
            'customer_id' => [
                $finalize ? 'required' : 'nullable',
                Rule::exists('contacts', 'id')
                    ->where('organization_id', $orgId)
                    ->whereNull('deleted_at'),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'justificatif' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.document').'|max:'.config('uploads.max_size.document'),
        ];
    }

    /** @return array<int, \Closure(Validator): void> */
    public function after(): array
    {
        if (! $this->boolean('finalize')) {
            return [];
        }

        return [function (Validator $validator): void {
            $total = '0.00';

            foreach ((array) $this->input('lines', []) as $line) {
                $type = InvoiceLineType::tryFrom((string) ($line['type'] ?? InvoiceLineType::Item->value));

                if ($type === null || ! $type->hasAmount()) {
                    continue;
                }

                $amount = Money::multiply2(
                    (string) ($line['quantity'] ?? '0'),
                    (string) ($line['unit_price'] ?? '0'),
                );

                if ($type === InvoiceLineType::Discount) {
                    $total = Money::subtract($total, $amount);
                } else {
                    $total = Money::add($total, $amount);
                }
            }

            if (! Money::isPositive($total)) {
                $validator->errors()->add('lines', __('app.invoice_total_must_be_positive'));
            }
        }];
    }
}
