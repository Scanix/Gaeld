<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreBudgetRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'account_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->where(fn ($query) => $query
                    ->where('organization_id', $orgId)
                    ->where('is_active', true)
                ),
            ],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'monthly_amount' => ['required', 'numeric', 'min:0', 'max:99999999.99'],
        ];
    }
}
