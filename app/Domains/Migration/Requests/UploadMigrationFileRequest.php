<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UploadMigrationFileRequest extends FormRequest
{
    public function authorize(): bool
    {
        $session = $this->route('session');

        return $session instanceof MigrationSession
            && $this->user()?->can('update', $session) === true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(MigrationRegistry $registry): array
    {
        $session = $this->route('session');
        $parser = $session instanceof MigrationSession
            ? $registry->getParser($session->platform)
            : null;
        $acceptedExtensions = $parser?->acceptedExtensions() ?? [];
        $supportedDataTypes = $parser?->supportedDataTypes() ?? [];

        $fileRules = $acceptedExtensions === []
            ? ['prohibited']
            : [
                'required',
                'file',
                'extensions:'.implode(',', $acceptedExtensions),
                'mimes:'.implode(',', $acceptedExtensions),
                'max:'.config('uploads.max_size.import'),
            ];

        return [
            'file' => $fileRules,
            'data_type' => [
                'required',
                'string',
                Rule::in(array_map(
                    fn (DataType $dataType): string => $dataType->value,
                    $supportedDataTypes,
                )),
            ],
            'column_mapping' => ['nullable', 'array', 'max:100'],
            'column_mapping.*' => ['nullable', 'integer', 'min:0', 'max:1000'],
            'delimiter' => ['nullable', 'string', Rule::in([',', ';', "\t", '|'])],
        ];
    }
}
