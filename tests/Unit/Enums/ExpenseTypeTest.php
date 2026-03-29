<?php

namespace Tests\Unit\Enums;

use App\Domains\Expenses\Enums\ExpenseType;
use Tests\TestCase;

class ExpenseTypeTest extends TestCase
{
    public function test_invoice_is_not_credit_note(): void
    {
        $this->assertFalse(ExpenseType::Invoice->isCreditNote());
    }

    public function test_credit_note_is_credit_note(): void
    {
        $this->assertTrue(ExpenseType::CreditNote->isCreditNote());
    }

    public function test_invoice_value(): void
    {
        $this->assertSame('invoice', ExpenseType::Invoice->value);
    }

    public function test_credit_note_value(): void
    {
        $this->assertSame('credit_note', ExpenseType::CreditNote->value);
    }

    public function test_labels_are_translatable(): void
    {
        foreach (ExpenseType::cases() as $case) {
            $this->assertNotEmpty($case->label());
        }
    }
}
