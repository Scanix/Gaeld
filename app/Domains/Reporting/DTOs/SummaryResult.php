<?php

namespace App\Domains\Reporting\DTOs;

/**
 * Typed result for aggregate count/total summary queries.
 *
 * Replaces the bare `object` return from unpaidSummary() and pendingSummary()
 * with an explicit shape that callers can rely on without null-coalescing.
 */
readonly class SummaryResult
{
    public function __construct(
        public int $count,
        public string $total,
    ) {}
}
