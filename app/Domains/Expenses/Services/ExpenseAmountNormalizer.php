<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Expenses\DTOs\ExpenseAmountBreakdown;
use App\Domains\Expenses\Enums\ExpenseAmountBasis;
use App\Support\Money;

class ExpenseAmountNormalizer
{
    public function normalize(
        string $organizationId,
        string $amount,
        ?string $vatRateId,
        ExpenseAmountBasis $basis,
    ): ExpenseAmountBreakdown {
        $enteredAmount = Money::normalize($amount);
        $vatRate = $this->vatRate($organizationId, $vatRateId);

        if ($vatRate === null || bccomp(Money::of($vatRate), '0', 2) === 0) {
            return new ExpenseAmountBreakdown(
                netAmount: $enteredAmount,
                vatAmount: Money::zero(),
                grossAmount: $enteredAmount,
            );
        }

        if ($basis === ExpenseAmountBasis::Gross) {
            $multiplier = bcadd('1', bcdiv($vatRate, '100', 8), 8);
            $netAmount = Money::divideRounded($enteredAmount, $multiplier);
            $vatAmount = Money::subtract($enteredAmount, $netAmount);

            return new ExpenseAmountBreakdown(
                netAmount: $netAmount,
                vatAmount: $vatAmount,
                grossAmount: $enteredAmount,
            );
        }

        $vatAmount = Money::percentage($enteredAmount, $vatRate);

        return new ExpenseAmountBreakdown(
            netAmount: $enteredAmount,
            vatAmount: $vatAmount,
            grossAmount: Money::add($enteredAmount, $vatAmount),
        );
    }

    private function vatRate(string $organizationId, ?string $vatRateId): ?string
    {
        if ($vatRateId === null || $vatRateId === '') {
            return null;
        }

        $rate = VatRate::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->whereKey($vatRateId)
            ->value('rate');

        return $rate === null ? null : (string) $rate;
    }
}
