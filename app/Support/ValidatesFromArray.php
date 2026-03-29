<?php

namespace App\Support;

/**
 * Provides a `fromArray()` guard that throws when required keys are missing.
 */
trait ValidatesFromArray
{
    /**
     * @param  array<string>  $required
     *
     * @throws \InvalidArgumentException
     */
    protected static function assertRequired(array $data, array $required): void
    {
        $missing = array_diff($required, array_keys($data));

        if (! empty($missing)) {
            throw new \InvalidArgumentException(
                sprintf('Missing required keys for %s: %s', static::class, implode(', ', $missing))
            );
        }
    }
}
