<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOpeningBalancesRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'date' => ['required', 'date'],
            'reference' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_posted' => ['sometimes', 'boolean'],
            'balances' => ['required', 'array', 'min:1'],
            'balances.*.account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where('organization_id', $orgId),
            ],
            'balances.*.amount' => ['required', 'numeric', 'max:99999999999.99', 'min:-99999999999.99'],
        ];
    }
}
