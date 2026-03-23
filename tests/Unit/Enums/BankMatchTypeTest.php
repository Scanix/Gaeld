<?php

namespace Tests\Unit\Enums;

use App\Domains\Banking\Enums\BankMatchType;
use PHPUnit\Framework\TestCase;

class BankMatchTypeTest extends TestCase
{
    public function test_cases_have_correct_values(): void
    {
        $this->assertSame('qr_reference', BankMatchType::QrReference->value);
        $this->assertSame('amount_customer', BankMatchType::AmountCustomer->value);
        $this->assertSame('heuristic', BankMatchType::Heuristic->value);
    }

    public function test_from_valid_values(): void
    {
        $this->assertSame(BankMatchType::QrReference, BankMatchType::from('qr_reference'));
        $this->assertSame(BankMatchType::AmountCustomer, BankMatchType::from('amount_customer'));
        $this->assertSame(BankMatchType::Heuristic, BankMatchType::from('heuristic'));
    }
}
