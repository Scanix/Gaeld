<?php

namespace App\Domains\Api\Requests;

use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrgTokenRequest extends FormRequest
{
    /**
     * Defense-in-depth: only users who can manage the current organisation may mint org tokens.
     */
    public function authorize(): bool
    {
        $org = app(CurrentOrganization::class);

        if (! $org->isBound() || $this->user() === null) {
            return false;
        }

        return $this->user()->can('manageUsers', $org->get());
    }

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
