<?php

namespace App\Domains\Expenses\Enums;

/** Distinguishes regular expenses from supplier credit notes. */
enum ExpenseType: string
{
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';

    public function label(): string
    {
        return match ($this) {
            self::Invoice => __('app.expense_type_invoice'),
            self::CreditNote => __('app.expense_type_credit_note'),
        };
    }

    public function isCreditNote(): bool
    {
        return $this === self::CreditNote;
    }
}
