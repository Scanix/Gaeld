<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Models\ExpenseCategory;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ExpenseCategoryQuery
{
    /**
     * @return Collection<int, ExpenseCategory>
     */
    public static function forSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "expense_categories_select:{$orgId}",
            3600,
            fn () => ExpenseCategory::orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    /**
     * @return Collection<int, ExpenseCategory>
     */
    public static function all(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "expense_categories_all:{$orgId}",
            3600,
            fn () => ExpenseCategory::orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
