<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReopenFiscalYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('reopenYear', Account::class) ?? false;
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        $organizationId = app(CurrentOrganization::class)->id();

        return [
            'fiscal_year_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('fiscal_years', 'id')->where('organization_id', $organizationId),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
