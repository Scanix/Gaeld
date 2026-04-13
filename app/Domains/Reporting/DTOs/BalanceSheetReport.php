<?php

namespace App\Domains\Reporting\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable value object representing a balance sheet (assets, liabilities, equity).
 *
 * @implements Arrayable<string, mixed>
 */
readonly class BalanceSheetReport implements Arrayable, JsonSerializable
{
    /**
     * @param  ReportAccountLine[]  $assets
     * @param  ReportAccountLine[]  $liabilities
     * @param  ReportAccountLine[]  $equity
     */
    public function __construct(
        public string $asOfDate,
        public array $assets,
        public string $totalAssets,
        public array $liabilities,
        public string $totalLiabilities,
        public array $equity,
        public string $totalEquity,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'as_of_date' => $this->asOfDate,
            'assets' => [
                'accounts' => array_map(fn (ReportAccountLine $line) => $line->toArray(), $this->assets),
                'total' => $this->totalAssets,
            ],
            'liabilities' => [
                'accounts' => array_map(fn (ReportAccountLine $line) => $line->toArray(), $this->liabilities),
                'total' => $this->totalLiabilities,
            ],
            'equity' => [
                'accounts' => array_map(fn (ReportAccountLine $line) => $line->toArray(), $this->equity),
                'total' => $this->totalEquity,
            ],
        ];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
