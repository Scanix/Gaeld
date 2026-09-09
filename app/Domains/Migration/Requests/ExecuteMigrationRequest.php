<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\DataType;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteMigrationRequest extends FormRequest
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
        $connector = $session instanceof MigrationSession
            ? $registry->getConnector($session->platform)
            : null;
        $supportedDataTypes = $parser?->supportedDataTypes()
            ?? $connector?->supportedDataTypes()
            ?? [];

        return [
            'data_types' => ['required', 'array', 'min:1', 'max:'.count($supportedDataTypes)],
            'data_types.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_map(
                    fn (DataType $dataType): string => $dataType->value,
                    $supportedDataTypes,
                )),
            ],
            'account_mappings' => 'nullable|array',
            'account_mappings.*.source_code' => 'required_with:account_mappings|string',
            'account_mappings.*.target_account_id' => 'required_with:account_mappings|uuid',
            'fiscal_year_start' => 'nullable|date',
        ];
    }
}
