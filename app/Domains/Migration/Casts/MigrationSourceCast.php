<?php

namespace App\Domains\Migration\Casts;

use App\Domains\Migration\Enums\Platform;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Keeps built-in sources as enums while allowing plugins to use string keys.
 *
 * @implements CastsAttributes<Platform|string|null, Platform|string|null>
 */
final class MigrationSourceCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): Platform|string|null
    {
        if ($value === null) {
            return null;
        }

        return Platform::tryFrom((string) $value) ?? (string) $value;
    }

    /**
     * @return array<string, string|null>
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        return [$key => $value instanceof Platform ? $value->value : $value];
    }
}
