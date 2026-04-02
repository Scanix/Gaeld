<?php

namespace App\Domains\Invoicing\Enums;

/** Payment method used to settle an invoice or expense. */
enum PaymentMethod: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Bank => __('app.payment_method_bank'),
            self::Cash => __('app.payment_method_cash'),
            self::Card => __('app.payment_method_card'),
        };
    }
}
