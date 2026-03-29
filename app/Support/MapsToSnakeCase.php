<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Converts camelCase DTO properties to snake_case arrays via `toArray()`.
 */
trait MapsToSnakeCase
{
    public function toArray(): array
    {
        $result = [];

        foreach (get_object_vars($this) as $property => $value) {
            $result[Str::snake($property)] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return $result;
    }
}
