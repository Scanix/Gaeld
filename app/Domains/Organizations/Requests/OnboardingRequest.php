<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Organizations\Enums\BusinessType;
use Illuminate\Validation\Rule;

class OnboardingRequest extends StoreOrganizationRequest
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
        $validTemplateKeys = app(ChartTemplateService::class)->validKeys();

        return array_merge(parent::rules(), [
            'currency' => 'required|string|size:3',
            'locale' => ['required', 'string', Rule::in(config('accounting.supported_locales'))],
            'chart_of_accounts' => ['required', 'string', Rule::in([...$validTemplateKeys, 'none'])],
            'business_type' => ['nullable', 'string', Rule::in(BusinessType::values())],
        ]);
    }
}
