<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Models\ExpenseCategory;

class ExpenseCategoryResolver
{
    public function resolve(
        string $organizationId,
        ?string $categoryId = null,
        ?string $categoryName = null,
    ): ?ExpenseCategory {
        $query = ExpenseCategory::withoutGlobalScopes()
            ->where('organization_id', $organizationId);

        if ($categoryId !== null) {
            $category = (clone $query)->whereKey($categoryId)->first();

            if ($category !== null) {
                return $category;
            }
        }

        if ($categoryName === null || $categoryName === '') {
            return null;
        }

        return $query->where('name', $categoryName)->first();
    }
}
