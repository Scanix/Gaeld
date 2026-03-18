<?php

namespace App\Domains\Accounting;

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
}
