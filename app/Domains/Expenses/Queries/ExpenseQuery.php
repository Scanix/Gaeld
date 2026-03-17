<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Models\Expense;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class ExpenseQuery
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Expense::query(), $request)
            ->allowedSorts(['date', 'amount', 'category', 'vendor', 'status'], 'date', 'desc')
            ->allowedFilters(['status', 'category'])
            ->searchable(['description', 'vendor', 'category'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
