<?php

namespace Tests\Unit;

use App\Domains\Accounting\Constants\AccountCode;
use PHPUnit\Framework\TestCase;

class AccountCodeTest extends TestCase
{
    public function test_constants_have_expected_values(): void
    {
        $this->assertSame('1100', AccountCode::ACCOUNTS_RECEIVABLE);
        $this->assertSame('1020', AccountCode::BANK_CASH);
        $this->assertSame('3000', AccountCode::REVENUE);
        $this->assertSame('6530', AccountCode::GENERAL_EXPENSE);
        $this->assertSame('3', AccountCode::REVENUE_PREFIX);
        $this->assertSame(['4', '5', '6'], AccountCode::EXPENSE_PREFIXES);
    }

    public function test_is_revenue_with_revenue_codes(): void
    {
        $this->assertTrue(AccountCode::isRevenue('3000'));
        $this->assertTrue(AccountCode::isRevenue('3100'));
        $this->assertTrue(AccountCode::isRevenue('3999'));
    }

    public function test_is_revenue_with_non_revenue_codes(): void
    {
        $this->assertFalse(AccountCode::isRevenue('1100'));
        $this->assertFalse(AccountCode::isRevenue('4000'));
        $this->assertFalse(AccountCode::isRevenue('6530'));
    }

    public function test_is_expense_with_expense_codes(): void
    {
        $this->assertTrue(AccountCode::isExpense('4000'));
        $this->assertTrue(AccountCode::isExpense('5100'));
        $this->assertTrue(AccountCode::isExpense('6530'));
    }

    public function test_is_expense_with_non_expense_codes(): void
    {
        $this->assertFalse(AccountCode::isExpense('1100'));
        $this->assertFalse(AccountCode::isExpense('3000'));
        $this->assertFalse(AccountCode::isExpense('7000'));
    }
}
