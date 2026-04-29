<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConsolidationGroupRequest extends FormRequest
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
        return [
            'name' => ['required', 'string', 'max:255'],
            'member_organization_ids' => ['required', 'array', 'min:1'],
            'member_organization_ids.*' => [
                'required',
                'uuid',
                'distinct',
                Rule::exists('organizations', 'id'),
            ],
            'base_currency' => ['required', 'string', 'size:3', 'alpha'],
        ];
    }
}
