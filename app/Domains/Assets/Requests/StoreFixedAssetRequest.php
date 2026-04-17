<?php

namespace App\Domains\Assets\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreFixedAssetRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'purchase_date' => ['required', 'date'],
            'purchase_amount' => ['required', 'numeric', 'min:0.01'],
            'useful_life_years' => ['required', 'integer', 'min:1'],
            'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_method' => ['required', Rule::in(['linear', 'declining_balance'])],
            'asset_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('organization_id', $orgId)],
            'depreciation_expense_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('organization_id', $orgId)],
            'accumulated_depreciation_account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('organization_id', $orgId)],
        ];
    }
}
