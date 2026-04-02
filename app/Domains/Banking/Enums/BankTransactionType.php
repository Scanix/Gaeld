<?php

namespace App\Domains\Banking\Enums;

/** Direction of a bank transaction: debit (outgoing) or credit (incoming). */
enum BankTransactionType: string
{
    case Credit = 'credit';
    case Debit = 'debit';

    public function label(): string
    {
        return match ($this) {
            self::Credit => __('app.bank_transaction_type_credit'),
            self::Debit => __('app.bank_transaction_type_debit'),
        };
    }
}
