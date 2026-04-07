<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateInvoiceApiRequest extends FormRequest
{
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'customer_id' => [
                'sometimes',
                'uuid',
                Rule::exists('customers', 'uuid')->where('organization_id', $orgId),
            ],
            'number' => 'nullable|string|max:50',
            'issue_date' => 'sometimes|date',
            'due_date' => 'nullable|date|after_or_equal:issue_date',
            'currency' => 'sometimes|string|size:3',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'sometimes|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $orgId),
            ],
        ];
    }
}
