<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\DataType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExecuteMigrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'data_types' => 'required|array|min:1',
            'data_types.*' => ['required', 'string', Rule::in(DataType::values())],
            'account_mappings' => 'nullable|array',
            'account_mappings.*.source_code' => 'required_with:account_mappings|string',
            'account_mappings.*.target_account_id' => 'required_with:account_mappings|uuid',
            'fiscal_year_start' => 'nullable|date',
        ];
    }
}
