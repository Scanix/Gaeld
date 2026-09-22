<?php

namespace App\Domains\Expenses\DTOs;

readonly class ExpenseAmountBreakdown
{
    public function __construct(
        public string $netAmount,
        public string $vatAmount,
        public string $grossAmount,
    ) {}
}
