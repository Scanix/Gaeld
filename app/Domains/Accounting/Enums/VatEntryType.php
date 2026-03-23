<?php

namespace App\Domains\Accounting\Enums;

enum VatEntryType: string
{
    case Input = 'input';   // VAT on purchases (Vorsteuer)
    case Output = 'output'; // VAT on sales (Umsatzsteuer)
}
