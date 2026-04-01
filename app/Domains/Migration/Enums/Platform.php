<?php

namespace App\Domains\Migration\Enums;

/**
 * Source platforms supported for data migration.
 */
enum Platform: string
{
    case Bexio = 'bexio';
    case Banana = 'banana';
    case Abacus = 'abacus';
    case GenericCsv = 'generic_csv';
    case Manual = 'manual';

    /**
     * Whether this platform parser is still work-in-progress.
     */
    public function isWip(): bool
    {
        return match ($this) {
            self::Banana, self::Abacus => true,
            default => false,
        };
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
