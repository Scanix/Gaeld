<?php

namespace App\Domains\Banking\Enums;

/** How a bank transaction was matched to a document (QR, amount, manual, etc.). */
enum BankMatchType: string
{
    case QrReference = 'qr_reference';
    case AmountCustomer = 'amount_customer';
    case Heuristic = 'heuristic';

    public function label(): string
    {
        return match ($this) {
            self::QrReference => __('app.bank_match_type_qr_reference'),
            self::AmountCustomer => __('app.bank_match_type_amount_customer'),
            self::Heuristic => __('app.bank_match_type_heuristic'),
        };
    }
}
