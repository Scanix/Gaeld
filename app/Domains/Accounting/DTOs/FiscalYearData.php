<?php

namespace App\Domains\Accounting\DTOs;

use App\Support\ValidatesFromArray;

/**
 * Value object describing a fiscal year to create or update.
 */
readonly class FiscalYearData
{
    use ValidatesFromArray;

    public function __construct(
        public string $name,
        public string $startDate,
        public string $endDate,
    ) {}

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['name', 'start_date', 'end_date']);

        return new self(
            name: (string) $data['name'],
            startDate: (string) $data['start_date'],
            endDate: (string) $data['end_date'],
        );
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
        ];
    }
}
