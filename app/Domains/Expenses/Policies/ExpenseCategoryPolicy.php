<?php

namespace App\Domains\Expenses\Policies;

use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for expense categories.
 *
 * Categories are read by any organization member (needed to populate the
 * category selector when creating an expense); only organization editors
 * may create/delete them, matching ExpenseCategoryController's existing
 * store()/destroy() checks against Organization::update.
 */
class ExpenseCategoryPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user);
    }

    public function view(User $user, ExpenseCategory $expenseCategory): bool
    {
        return $this->belongsToOrganization($user, $expenseCategory);
    }
}
