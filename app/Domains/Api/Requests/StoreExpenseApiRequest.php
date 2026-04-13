<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreExpenseApiRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $org = app(CurrentOrganization::class);
        $orgId = $org->isBound() ? $org->id() : 0;

        return [
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                'uuid',
                Rule::exists('vat_rates', 'uuid')->where('organization_id', $orgId),
            ],
            'vendor' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
        ];
    }
}
