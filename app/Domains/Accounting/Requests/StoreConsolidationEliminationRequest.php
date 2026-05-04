<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\ConsolidationGroup;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsolidationEliminationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $group = $this->route('group');
        $memberIds = [];

        if ($group instanceof ConsolidationGroup) {
            /** @var array<int, string> $memberOrganizationIds */
            $memberOrganizationIds = (array) $group->member_organization_ids;
            $memberIds = array_values(array_unique([
                $group->organization_id,
                ...$memberOrganizationIds,
            ]));
        }

        return [
            'account_debit_id' => [
                'required',
                'integer',
                Rule::exists('accounts', 'id')->whereIn('organization_id', $memberIds),
            ],
            'account_credit_id' => [
                'required',
                'integer',
                'different:account_debit_id',
                Rule::exists('accounts', 'id')->whereIn('organization_id', $memberIds),
            ],
            'amount' => ['required', 'numeric', 'gt:0'],
            'fiscal_year' => ['required', 'integer', 'min:2000', 'max:2099'],
            'description' => ['nullable', 'string', 'max:255'],
        ];
    }
}
