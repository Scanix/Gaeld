<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Expenses\Models\ExpenseCategory;

class ExpenseAccountResolver
{
    public function resolve(
        string $organizationId,
        ?int $accountId = null,
        ?string $accountCode = null,
        ?ExpenseCategory $category = null,
    ): ?Account {
        $query = Account::withoutGlobalScopes()
            ->where('organization_id', $organizationId)
            ->where('type', AccountType::Expense)
            ->where('is_active', true);

        if ($accountId !== null) {
            $account = (clone $query)->whereKey($accountId)->first();

            if ($account !== null) {
                return $account;
            }
        }

        if ($accountCode !== null && $accountCode !== '') {
            $account = (clone $query)->where('code', $accountCode)->first();

            if ($account !== null) {
                return $account;
            }
        }

        if ($category?->default_expense_account_id === null) {
            return null;
        }

        return $query->whereKey($category->default_expense_account_id)->first();
    }
}
