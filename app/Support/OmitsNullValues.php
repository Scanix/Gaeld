<?php

namespace App\Support;

use BackedEnum;
use Illuminate\Support\Str;

/**
 * Like MapsToSnakeCase, but strips null values from the output.
 *
 * Useful for Update DTOs where null means "field was not provided"
 * and should not overwrite existing non-null database values.
 */
trait OmitsNullValues
{
    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $result = [];

        foreach (get_object_vars($this) as $property => $value) {
            if ($value === null) {
                continue;
            }

            $result[Str::snake($property)] = $value instanceof BackedEnum ? $value->value : $value;
        }

        return $result;
    }
}
