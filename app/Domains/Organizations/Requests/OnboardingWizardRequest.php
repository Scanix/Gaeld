<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Organizations\Enums\OrganizationModule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the post-signup onboarding wizard submission.
 *
 * All steps after module selection are optional: fiscal year and bank
 * account are only created when their key fields are provided, so a user
 * can complete the wizard with as little as a module selection.
 */
class OnboardingWizardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'business_type' => ['nullable', 'string', Rule::in(BusinessType::values())],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['boolean'],

            'legal_name' => ['nullable', 'string', 'max:255'],
            'address' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:100'],
            'postal_code' => ['nullable', 'string', 'max:10'],
            'canton' => ['nullable', 'string', 'size:2'],
            'vat_number' => ['nullable', 'string', 'max:50'],

            'fiscal_year_name' => ['nullable', 'string', 'max:255', 'required_with:fiscal_year_start,fiscal_year_end'],
            'fiscal_year_start' => ['nullable', 'date', 'required_with:fiscal_year_end'],
            'fiscal_year_end' => ['nullable', 'date', 'after:fiscal_year_start', 'required_with:fiscal_year_start'],

            'bank_account_name' => ['nullable', 'string', 'max:255'],
            'bank_name' => ['nullable', 'string', 'max:255'],
            'iban' => ['nullable', 'string', 'max:34'],
        ];
    }

    /**
     * Only the whitelisted module keys, coerced to booleans.
     *
     * @return array<string, bool>
     */
    public function modules(): array
    {
        return collect((array) $this->input('modules', []))
            ->only(OrganizationModule::values())
            ->map(fn ($value): bool => (bool) $value)
            ->all();
    }
}
