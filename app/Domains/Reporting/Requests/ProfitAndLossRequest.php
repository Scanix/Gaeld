<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfitAndLossRequest extends FormRequest
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
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date', 'after_or_equal:from'],
            'compare_from' => ['nullable', 'date'],
            'compare_to' => ['nullable', 'date', 'required_with:compare_from', 'after_or_equal:compare_from'],
        ];
    }
}
