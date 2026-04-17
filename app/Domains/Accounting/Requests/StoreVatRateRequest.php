<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreVatRateRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:100'],
            'rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique('vat_rates', 'code')->where('organization_id', $orgId),
            ],
            'is_default' => ['boolean'],
        ];
    }
}
