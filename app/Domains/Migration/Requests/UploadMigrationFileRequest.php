<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\DataType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMigrationFileRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'file' => 'required|file|max:'.config('uploads.max_size.document'),
            'data_type' => ['required', 'string', Rule::in(DataType::values())],
            'column_mapping' => 'nullable|array',
            'column_mapping.*' => 'nullable|integer|min:0',
            'delimiter' => 'nullable|string|max:1',
        ];
    }
}
