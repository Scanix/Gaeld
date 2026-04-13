<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Enums\Platform;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartMigrationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'platform' => ['required', 'string', Rule::in(Platform::values())],
        ];
    }
}
