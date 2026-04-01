<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

class ExpenseCategoryQuery
{
    public static function forSelect(): Collection
    {
        $orgId = app(\App\Domains\Organizations\Services\CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "expense_categories_select:{$orgId}",
            3600,
            fn () => ExpenseCategory::orderBy('sort_order')
                ->orderBy('name')
                ->get(['id', 'name'])
        );
    }

    public static function all(): Collection
    {
        $orgId = app(\App\Domains\Organizations\Services\CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:reference"])->remember(
            "expense_categories_all:{$orgId}",
            3600,
            fn () => ExpenseCategory::orderBy('sort_order')
                ->orderBy('name')
                ->get()
        );
    }
}
