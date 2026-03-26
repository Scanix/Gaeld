<?php

namespace App\Domains\Banking\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCamtRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'camt_file' => 'required|file|max:'.config('uploads.max_size.document'),
        ];
    }
}
