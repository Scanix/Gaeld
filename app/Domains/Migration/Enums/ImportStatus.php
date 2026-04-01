<?php

namespace App\Domains\Migration\Enums;

/**
 * Status of a migration session or individual data type import.
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyCompleted = 'partially_completed';
    case Reversing = 'reversing';
    case Reversed = 'reversed';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
