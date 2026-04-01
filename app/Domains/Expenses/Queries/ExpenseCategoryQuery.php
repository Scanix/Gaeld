<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Models\ExpenseCategory;
use Illuminate\Database\Eloquent\Collection;

class ExpenseCategoryQuery
{
    public static function forSelect(): Collection
    {
        return ExpenseCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name']);
    }

    public static function all(): Collection
    {
        return ExpenseCategory::orderBy('sort_order')
            ->orderBy('name')
            ->get();
    }
}
