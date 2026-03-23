<?php

namespace Tests\Unit\Enums;

use App\Domains\Accounting\Enums\AccountType;
use PHPUnit\Framework\TestCase;

class AccountTypeTest extends TestCase
{
    public function test_asset_is_debit_normal(): void
    {
        $this->assertTrue(AccountType::Asset->isDebitNormal());
    }

    public function test_expense_is_debit_normal(): void
    {
        $this->assertTrue(AccountType::Expense->isDebitNormal());
    }

    public function test_liability_is_credit_normal(): void
    {
        $this->assertFalse(AccountType::Liability->isDebitNormal());
    }

    public function test_revenue_is_credit_normal(): void
    {
        $this->assertFalse(AccountType::Revenue->isDebitNormal());
    }

    public function test_equity_is_credit_normal(): void
    {
        $this->assertFalse(AccountType::Equity->isDebitNormal());
    }

    public function test_all_cases_have_string_values(): void
    {
        $expected = ['asset', 'liability', 'equity', 'revenue', 'expense'];
        $values = array_map(fn (AccountType $t) => $t->value, AccountType::cases());

        $this->assertSame($expected, $values);
    }
}
