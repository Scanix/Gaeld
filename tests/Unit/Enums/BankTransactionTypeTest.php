<?php

namespace Tests\Unit\Enums;

use App\Domains\Banking\Enums\BankTransactionType;
use PHPUnit\Framework\TestCase;

class BankTransactionTypeTest extends TestCase
{
    public function test_credit_has_correct_value(): void
    {
        $this->assertSame('credit', BankTransactionType::Credit->value);
    }

    public function test_debit_has_correct_value(): void
    {
        $this->assertSame('debit', BankTransactionType::Debit->value);
    }

    public function test_from_valid_value(): void
    {
        $this->assertSame(BankTransactionType::Credit, BankTransactionType::from('credit'));
        $this->assertSame(BankTransactionType::Debit, BankTransactionType::from('debit'));
    }

    public function test_try_from_invalid_returns_null(): void
    {
        $this->assertNull(BankTransactionType::tryFrom('invalid'));
    }
}
