<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

class StoreAccountRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $orgId = app(CurrentOrganization::class)->id();

        return [
            'code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('accounts', 'code')->where('organization_id', $orgId),
            ],
            'name' => 'required|string|max:255',
            'type' => ['required', new Enum(AccountType::class)],
            'parent_id' => [
                'nullable',
                'integer',
                Rule::exists('accounts', 'id')->where('organization_id', $orgId),
            ],
            'is_active' => 'boolean',
            'description' => 'nullable|string|max:1000',
        ];
    }
}
