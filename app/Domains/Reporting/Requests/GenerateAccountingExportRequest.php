<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class GenerateAccountingExportRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'fiscal_year' => ['required', 'digits:4', 'integer', 'min:2000', 'max:2100'],
        ];
    }
}
