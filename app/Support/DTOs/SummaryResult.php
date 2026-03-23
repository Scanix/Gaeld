<?php

namespace App\Support\DTOs;

/**
 * Typed result for aggregate count/total summary queries.
 *
 * Shared across domains so that Expenses and Invoicing can return typed
 * summaries without creating a dependency on the Reporting domain.
 */
readonly class SummaryResult
{
    public function __construct(
        public int $count,
        public string $total,
    ) {}
}
