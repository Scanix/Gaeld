<?php

namespace App\Domains\Api\Requests;

use App\Domains\Expenses\Validation\ExpenseSharedValidationRules;
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

        return array_merge(ExpenseSharedValidationRules::store(), [
            'vat_rate_id' => [
                'nullable',
                'uuid',
                Rule::exists('vat_rates', 'uuid')->where('organization_id', $orgId),
            ],
        ]);
    }
}
