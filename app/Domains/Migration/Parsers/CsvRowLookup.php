<?php

namespace App\Domains\Migration\Parsers;

trait CsvRowLookup
{
    /**
     * @param  string[]  $keys
     */
    private function findValue(array $row, array $keys): ?string
    {
        $lowered = array_change_key_case($row, CASE_LOWER);

        foreach ($keys as $key) {
            $key = strtolower($key);
            if (isset($lowered[$key]) && $lowered[$key] !== '') {
                return trim($lowered[$key]);
            }
        }

        return null;
    }
}
