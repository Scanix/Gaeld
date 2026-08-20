<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreYearEndClosingRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => [
                'nullable',
                'string',
                'uuid',
                Rule::exists('fiscal_years', 'id')
                    ->where('organization_id', app(CurrentOrganization::class)->id()),
            ],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
            'closing_date' => ['required', 'date'],
            'reference' => ['required', 'string', 'max:50'],
            'result_account_code' => ['required', 'string', 'max:20'],
        ];
    }
}
