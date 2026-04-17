<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Rules\ValidCostCenterParent;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCostCenterRequest extends FormRequest
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
        $costCenter = $this->route('costCenter');
        if (! $costCenter instanceof CostCenter) {
            abort(404);
        }

        return [
            'code' => [
                'required',
                'string',
                'max:30',
                Rule::unique('cost_centers', 'code')
                    ->where('organization_id', $costCenter->organization_id)
                    ->ignore($costCenter->id),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
            'parent_id' => [
                'sometimes',
                'nullable',
                'integer',
                Rule::exists('cost_centers', 'id')->where('organization_id', $costCenter->organization_id),
                new ValidCostCenterParent($costCenter->organization_id, $costCenter),
            ],
        ];
    }
}
