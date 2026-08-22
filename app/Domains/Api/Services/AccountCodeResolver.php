<?php

namespace App\Domains\Api\Services;

use App\Domains\Accounting\Models\Account;
use App\Support\Exceptions\DomainException;

/**
 * Resolves public chart-of-account codes inside the token's organization.
 */
final class AccountCodeResolver
{
    public function resolve(string $organizationId, string $accountCode): Account
    {
        $account = Account::query()
            ->where('organization_id', $organizationId)
            ->where('code', $accountCode)
            ->where('is_active', true)
            ->first();

        if ($account === null) {
            throw new DomainException("Account '{$accountCode}' was not found or is inactive.");
        }

        return $account;
    }
}
