<?php

namespace App\Domains\Reporting\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VatReportRequest extends FormRequest
{
    /**
     * Normalise the two alias pairs before validation.
     * The export dropdown sends `from`/`to`; the settlement form sends `from_date`/`to_date`.
     * Both are accepted and mapped to `from_date`/`to_date` for the rest of the request lifecycle.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('from') && ! $this->has('from_date')) {
            $this->merge(['from_date' => $this->input('from')]);
        }
        if ($this->has('to') && ! $this->has('to_date')) {
            $this->merge(['to_date' => $this->input('to')]);
        }
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
