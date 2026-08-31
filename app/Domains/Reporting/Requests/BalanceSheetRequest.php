<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BalanceSheetRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => [
                'nullable',
                'uuid',
                Rule::exists('fiscal_years', 'id')
                    ->where('organization_id', app(CurrentOrganization::class)->id()),
            ],
            'as_of_date' => ['nullable', 'date'],
            'compare_as_of_date' => ['nullable', 'date'],
        ];
    }
}
