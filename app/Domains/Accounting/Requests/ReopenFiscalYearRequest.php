<?php

namespace App\Domains\Accounting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReopenFiscalYearRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fiscal_year_id' => ['nullable', 'string', 'uuid', 'exists:fiscal_years,id'],
            'year' => ['required', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
