<?php

namespace Tests\Unit\Enums;

use App\Domains\Banking\Enums\MatchConfidence;
use PHPUnit\Framework\TestCase;

class MatchConfidenceTest extends TestCase
{
    public function test_qr_reference_is_highest(): void
    {
        $this->assertSame(100, MatchConfidence::QrReference->value);
    }

    public function test_confidence_ordering(): void
    {
        $this->assertGreaterThan(
            MatchConfidence::AmountAndCustomer->value,
            MatchConfidence::QrReference->value,
        );

        $this->assertGreaterThan(
            MatchConfidence::Heuristic->value,
            MatchConfidence::AmountAndCustomer->value,
        );
    }

    public function test_auto_expense_threshold_is_80(): void
    {
        $this->assertSame(80, MatchConfidence::AutoExpenseThreshold->value);
    }

    public function test_amount_tolerance_constant(): void
    {
        $this->assertSame('1.00', MatchConfidence::AMOUNT_TOLERANCE);
    }
}
