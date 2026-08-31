<?php

namespace App\Domains\Reporting\Requests;

use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateAccountingExportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => [
                'nullable',
                'uuid',
                Rule::exists('fiscal_years', 'id')
                    ->where('organization_id', app(CurrentOrganization::class)->id()),
            ],
            'fiscal_year' => [
                'nullable',
                'digits:4',
                'integer',
                'min:2000',
                'max:2100',
                'required_without:fiscal_year_id',
            ],
        ];
    }
}
