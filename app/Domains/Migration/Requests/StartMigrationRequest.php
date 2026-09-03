<?php

namespace App\Domains\Migration\Requests;

use App\Domains\Migration\Services\MigrationRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StartMigrationRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(MigrationRegistry $registry): array
    {
        return [
            'platform' => ['required', 'string', Rule::in($registry->availablePlatformKeys())],
        ];
    }
}
