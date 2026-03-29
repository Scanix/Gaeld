<?php

namespace App\Domains\Accounting\Enums;

/** Distinguishes input VAT (purchases) from output VAT (sales). */
enum VatEntryType: string
{
    case Input = 'input';   // VAT on purchases (Vorsteuer)
    case Output = 'output'; // VAT on sales (Umsatzsteuer)
}
