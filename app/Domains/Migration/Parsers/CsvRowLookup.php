<?php

namespace App\Domains\Migration\Parsers;

use App\Domains\Migration\Enums\DataType;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;

trait CsvRowLookup
{
    public function parse(UploadedFile $file, DataType $dataType): Collection
    {
        $content = $file->get();
        $rows = $this->parseCsv($content);

        if (empty($rows)) {
            return collect();
        }

        return collect($rows)->map(fn (array $row, int $index) => $this->mapRow($row, $index + 1, $dataType))
            ->filter();
    }

    /**
     * @param  array<string, mixed>  $row
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
