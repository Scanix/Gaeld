<?php

namespace App\Domains\Accounting\Enums;

/** Chart-of-accounts category (asset, liability, equity, revenue, expense). */
enum AccountType: string
{
    case Asset = 'asset';
    case Liability = 'liability';
    case Equity = 'equity';
    case Revenue = 'revenue';
    case Expense = 'expense';

    public function isDebitNormal(): bool
    {
        return match ($this) {
            self::Asset, self::Expense => true,
            default => false,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Asset => __('app.account_type_asset'),
            self::Liability => __('app.account_type_liability'),
            self::Equity => __('app.account_type_equity'),
            self::Revenue => __('app.account_type_revenue'),
            self::Expense => __('app.account_type_expense'),
        };
    }
}
