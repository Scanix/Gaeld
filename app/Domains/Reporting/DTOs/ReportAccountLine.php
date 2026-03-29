<?php

namespace App\Domains\Reporting\DTOs;

/**
 * Single account line in a financial report, with optional budget comparison.
 */
readonly class ReportAccountLine
{
    public function __construct(
        public string $code,
        public string $name,
        public string $balance,
        public ?string $budgetAmount = null,
        public ?string $budgetVariance = null,
    ) {}

    public function toArray(): array
    {
        $data = [
            'code' => $this->code,
            'name' => $this->name,
            'balance' => $this->balance,
        ];

        if ($this->budgetAmount !== null) {
            $data['budget_amount'] = $this->budgetAmount;
            $data['budget_variance'] = $this->budgetVariance;
        }

        return $data;
    }
}
