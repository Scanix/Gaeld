<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateExpenseApiRequest extends FormRequest
{
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'category' => 'sometimes|string|max:100',
            'amount' => 'sometimes|numeric|min:0.01',
            'date' => 'sometimes|date',
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
