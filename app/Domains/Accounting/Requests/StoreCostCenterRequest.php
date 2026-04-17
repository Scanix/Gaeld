<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Rules\ValidCostCenterParent;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCostCenterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('cost_centers', 'code')->where('organization_id', $orgId),
            ],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('cost_centers', 'id')->where('organization_id', $orgId),
                new ValidCostCenterParent($orgId),
            ],
        ];
    }
}
