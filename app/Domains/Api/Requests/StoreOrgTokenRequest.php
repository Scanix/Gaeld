<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgTokenRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => ['string', Rule::in(['*', ...Permission::values()])],
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ];
    }
}
