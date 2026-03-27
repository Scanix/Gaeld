<?php

namespace App\Domains\Accounting\Constants;

final class AccountCode
{
    public const ACCOUNTS_RECEIVABLE = '1100';

    public const BANK_CASH = '1020';

    public const REVENUE = '3000';

    public const ROUNDING_DIFFERENCE = '3900';

    public const VAT_OUTPUT = '2200';

    public const SOCIAL_CHARGES = '5700';

    public const GENERAL_EXPENSE = '6530';

    public const ASSET_DISPOSAL_GAIN = '7510';

    public const ASSET_DISPOSAL_LOSS = '7520';

    public const SALARIES = '5000';

    public const SOCIAL_CHARGES_EMPLOYER = '5700';

    public const AVS_PAYABLE = '2270';

    public const AC_PAYABLE = '2271';

    public const LPP_PAYABLE = '2272';

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
