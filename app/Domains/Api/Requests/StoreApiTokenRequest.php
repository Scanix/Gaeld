<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Enums\Permission;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreApiTokenRequest extends FormRequest
{
    /**
     * Personal token creation — only an authenticated user may create their own token.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'abilities' => 'array',
            'abilities.*' => ['string', Rule::in(array_column(Permission::cases(), 'value'))],
            'expires_in_days' => 'nullable|integer|min:1|max:365',
        ];
    }
}
