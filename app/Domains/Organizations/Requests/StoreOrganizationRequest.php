<?php

namespace App\Domains\Organizations\Requests;

use App\Domains\Accounting\Services\ChartTemplateService;
use App\Domains\Organizations\Enums\BusinessType;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrganizationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Organization::class);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $validTemplateKeys = app(ChartTemplateService::class)->validKeys();

        return [
            'name' => 'required|string|max:255',
            'legal_name' => 'nullable|string|max:255',
            'address' => 'nullable|string',
            'city' => 'nullable|string|max:100',
            'postal_code' => 'nullable|string|max:10',
            'canton' => 'nullable|string|size:2',
            'country' => 'nullable|string|size:2',
            'vat_number' => 'nullable|string|max:50',
            'currency' => 'required|string|size:3',
            'locale' => ['required', 'string', Rule::in(config('accounting.supported_locales'))],
            'chart_of_accounts' => ['required', 'string', Rule::in([...$validTemplateKeys, 'none'])],
            'business_type' => ['nullable', 'string', Rule::in(BusinessType::values())],
        ];
    }
}
