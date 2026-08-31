<?php

namespace App\Domains\Api\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImportCamtApiRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'camt_file' => ['required', 'file', 'max:'.config('uploads.max_size.document')],
        ];
    }
}
