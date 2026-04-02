<?php

namespace App\Domains\Accounting\Enums;

/** Distinguishes input VAT (purchases) from output VAT (sales). */
enum VatEntryType: string
{
    case Input = 'input';   // VAT on purchases (Vorsteuer)
    case Output = 'output'; // VAT on sales (Umsatzsteuer)

    public function label(): string
    {
        return match ($this) {
            self::Input => __('app.vat_entry_type_input'),
            self::Output => __('app.vat_entry_type_output'),
        };
    }
}
