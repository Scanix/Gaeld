<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VatReportRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        $this->merge([
            'from_date' => $this->input('from_date', $this->input('from')),
            'to_date' => $this->input('to_date', $this->input('to')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from_date' => ['required', 'date'],
            'to_date' => ['required', 'date', 'after:from_date'],
        ];
    }
}
