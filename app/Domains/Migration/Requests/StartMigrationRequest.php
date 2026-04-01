<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartMigrationRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(Platform::values())],
        ];
    }
}
