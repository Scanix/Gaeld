<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCamtRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'camt_file' => 'required|file|max:'.config('uploads.max_size.document'),
            'csv_mapping' => 'nullable|array',
            'csv_mapping.date' => 'required_if:csv_mapping,!null|integer|min:0',
            'csv_mapping.amount' => 'required_if:csv_mapping,!null|integer|min:0',
            'csv_mapping.description' => 'nullable|integer|min:0',
            'csv_mapping.reference' => 'nullable|integer|min:0',
            'csv_delimiter' => 'nullable|string|max:1',
        ];
    }
}
