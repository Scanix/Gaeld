<?php

namespace App\Domains\Accounting\ValueObjects;

/**
 * Named constants for chart-of-accounts codes used across the application.
 *
 * These follow the Swiss SME chart of accounts (Kontenrahmen KMU).
 * Using named constants prevents magic strings and enables IDE navigation.
 */
final class AccountCode
{
    public const ACCOUNTS_RECEIVABLE = '1100';
    public const BANK_CASH = '1020';
    public const REVENUE = '3000';
    public const GENERAL_EXPENSE = '6530';

    public const REVENUE_PREFIX = '3';
    public const EXPENSE_PREFIXES = ['4', '5', '6'];

    public static function isRevenue(string $code): bool
    {
        return str_starts_with($code, self::REVENUE_PREFIX);
    }

    public static function isExpense(string $code): bool
    {
        return in_array(substr($code, 0, 1), self::EXPENSE_PREFIXES, true);
    }
}
