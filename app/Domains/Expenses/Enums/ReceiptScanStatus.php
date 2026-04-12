<?php

namespace App\Domains\Expenses\Enums;

/** OCR receipt scan lifecycle: pending → completed | failed → validated. */
enum ReceiptScanStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
    case Failed = 'failed';
    case Validated = 'validated';
}
