<?php

namespace App\Domains\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePersonalTokenSettingsRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['array'],
            'abilities.*' => ['string', Rule::in(self::allowedAbilities())],
            'expires_in_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ];
    }

    /** @return array<int, string> */
    private static function allowedAbilities(): array
    {
        return [
            'customers:read',
            'customers:write',
            'invoices:read',
            'invoices:write',
            'expenses:read',
            'expenses:write',
            'accounts:read',
            'bank-accounts:read',
            'webhooks:read',
            'webhooks:write',
            '*',
        ];
    }
}
