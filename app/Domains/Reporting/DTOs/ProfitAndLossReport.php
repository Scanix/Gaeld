<?php

namespace App\Domains\Reporting\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Immutable value object representing a profit & loss (income) statement.
 */
readonly class ProfitAndLossReport implements Arrayable, JsonSerializable
{
    /**
     * @param  ReportAccountLine[]  $revenue
     * @param  ReportAccountLine[]  $expenses
     */
    public function __construct(
        public string $fromDate,
        public string $toDate,
        public array $revenue,
        public array $expenses,
        public string $totalRevenue,
        public string $totalExpenses,
        public string $netProfit,
        public ?array $comparison = null,
        public ?array $variance = null,
        public ?array $budget = null,
    ) {}

    public function toArray(): array
    {
        return [
            'period' => ['from' => $this->fromDate, 'to' => $this->toDate],
            'revenue' => array_map(fn (ReportAccountLine $line) => $line->toArray(), $this->revenue),
            'expenses' => array_map(fn (ReportAccountLine $line) => $line->toArray(), $this->expenses),
            'total_revenue' => $this->totalRevenue,
            'total_expenses' => $this->totalExpenses,
            'net_profit' => $this->netProfit,
            'comparison' => $this->comparison,
            'variance' => $this->variance,
            'budget' => $this->budget,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
