<?php

namespace App\Domains\Accounting\Queries;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class AccountQuery
{
    /**
     * @return Collection<int, Account>
     */
    public static function forSelect(?AccountType $type = null): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();
        $suffix = $type ? "_{$type->value}" : '';

        return Cache::tags(["org:{$orgId}:ledger"])->remember(
            "accounts_select{$suffix}:{$orgId}",
            600,
            fn () => Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->when($type, fn ($q) => $q->where('type', $type))
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
        );
    }

    /**
     * Asset accounts limited to the cash/bank class (Swiss KMU codes 1000-1099).
     *
     * Used when picking a ledger account for a bank account so the dropdown
     * doesn't list AR / VAT / inventory / fixed-asset accounts.
     *
     * @return Collection<int, Account>
     */
    public static function cashOrBankForSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:ledger"])->remember(
            "accounts_cash_or_bank_select:{$orgId}",
            600,
            fn () => Account::where('organization_id', $orgId)
                ->where('is_active', true)
                ->where('type', AccountType::Asset)
                ->where('code', '>=', '1000')
                ->where('code', '<', '1100')
                ->orderBy('code')
                ->get(['id', 'code', 'name', 'type'])
        );
    }
}
