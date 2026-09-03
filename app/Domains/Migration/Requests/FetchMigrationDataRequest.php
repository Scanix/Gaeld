<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FetchMigrationDataRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(MigrationRegistry $registry): array
    {
        $session = $this->route('session');
        $supportedTypes = $session instanceof MigrationSession
            ? $registry->getConnector($session->platform)?->supportedDataTypes() ?? []
            : [];

        return [
            'data_type' => [
                'required',
                'string',
                Rule::in(array_map(fn (DataType $dataType) => $dataType->value, $supportedTypes)),
            ],
        ];
    }
}
