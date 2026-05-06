<?php

namespace App\Domains\Invoicing\Requests\Concerns;

use App\Domains\Invoicing\Enums\InvoiceLineType;
use Illuminate\Validation\Rule;

trait InvoiceValidationRules
{
    /** @return array<string, mixed> */
    protected function sharedRules(string $orgId): array
    {
        $finalize = $this->boolean('finalize');

        return [
            'number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => [$finalize ? 'required' : 'nullable', 'date', 'after_or_equal:issue_date'],
            'currency' => 'string|size:3',
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
                Rule::exists('contacts', 'id')->where('organization_id', $orgId),
            ],
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
            'justificatif' => 'nullable|file|mimes:'.config('uploads.allowed_mimes.document').'|max:'.config('uploads.max_size.document'),
        ];
    }
}
