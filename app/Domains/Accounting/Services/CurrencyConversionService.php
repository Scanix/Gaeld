<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ExchangeRate;
use App\Support\Money;
use DomainException;

/**
 * Converts a document amount into the organization's ledger currency.
 *
 * The ledger itself is single-currency. A foreign invoice must not be
 * posted as if its amount were already in that currency.
 */
class CurrencyConversionService
{
    /**
     * @return array{amount: numeric-string, rate: numeric-string}
     */
    public function convert(string $organizationId, string $from, string $to, string $amount, string $date): array
    {
        $rate = $this->rate($organizationId, $from, $to, $date);

        return [
            'amount' => Money::round(bcmul($amount, $rate, 8)),
            'rate' => $rate,
        ];
    }

    /**
     * Units of $to per 1 unit of $from, using the newest rate on or before $date.
     *
     * @return numeric-string
     */
    public function rate(string $organizationId, string $from, string $to, string $date): string
    {
        $from = strtoupper($from);
        $to = strtoupper($to);

        if ($from === $to) {
            return '1.00000000';
        }

        $direct = $this->lookup($organizationId, $from, $to, $date);
        if ($direct !== null) {
            return $this->normalizeRate((string) $direct);
        }

        $inverse = $this->lookup($organizationId, $to, $from, $date);
        if ($inverse !== null && bccomp((string) $inverse, '0', 8) !== 0) {
            return bcdiv('1', (string) $inverse, 8);
        }

        throw new DomainException(
            "Cannot book {$from} into the {$to} ledger without an exchange rate on or before {$date}. Add one under Accounting → Exchange rates (fetch ECB rates, or enter EUR → {$to})."
        );
    }

    private function lookup(string $organizationId, string $from, string $to, string $date): ?string
    {
        $rate = ExchangeRate::query()
            ->where('organization_id', $organizationId)
            ->where('currency_from', $from)
            ->where('currency_to', $to)
            ->whereDate('date', '<=', $date)
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->value('rate');

        return $rate === null ? null : (string) $rate;
    }

    /**
     * @return numeric-string
     */
    private function normalizeRate(string $rate): string
    {
        return bcadd($rate, '0', 8);
    }
}
