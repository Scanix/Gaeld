<?php

namespace App\Domains\Migration\Enums;

/**
 * Importable data types supported by the migration system.
 */
enum DataType: string
{
    case Accounts = 'accounts';
    case OpeningBalances = 'opening_balances';
    case JournalEntries = 'journal_entries';
    case Contacts = 'contacts';
    case Invoices = 'invoices';
    case Expenses = 'expenses';
    case FixedAssets = 'fixed_assets';
    case YearEndClosing = 'year_end_closing';

    public function labelKey(): string
    {
        return "migration.data_types.{$this->value}";
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
