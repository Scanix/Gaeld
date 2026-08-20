<?php

namespace App\Domains\Accounting\DTOs;

use Illuminate\Support\Carbon;

/**
 * Immutable accounting period resolved from an explicit fiscal year or legacy
 * calendar-year fallback.
 */
readonly class FiscalYearPeriod
{
    public function __construct(
        public string $organizationId,
        public ?string $fiscalYearId,
        public string $label,
        public string $fromDate,
        public string $toDate,
        public bool $isLegacyFallback,
    ) {}

    public function containsDate(string $date): bool
    {
        $value = Carbon::parse($date)->toDateString();

        return $value >= $this->fromDate && $value <= $this->toDate;
    }
}
