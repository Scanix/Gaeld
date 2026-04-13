<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOrganizationSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('organization') ?? app(CurrentOrganization::class)->get());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'string|size:3',
            'locale' => ['string', Rule::in(config('accounting.supported_locales'))],
            'business_type' => ['nullable', 'string', Rule::in(BusinessType::values())],
            'require_two_factor' => 'sometimes|boolean',
            'default_payment_terms_days' => 'sometimes|integer|min:0|max:365',
        ];
    }
}
