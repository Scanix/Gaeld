<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Support\DTOs\SummaryResult;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class ExpenseQuery
{
    /**
     * @return LengthAwarePaginator<int, Expense>
     */
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Expense::query()->with('supplier'), $request)
            ->allowedSorts(['date', 'amount', 'category', 'vendor', 'status'], 'date', 'desc')
            ->allowedFilters(['status', 'category'])
            ->searchable(['description', 'vendor', 'category'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($expense) {
                if (empty($expense->vendor) && $expense->supplier) {
                    $expense->vendor = $expense->supplier->name;
                }

                return $expense;
            });
    }

    public static function yearlyTotal(string $orgId, int $year): string
    {
        return (string) Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->sum('amount');
    }

    public static function pendingSummary(string $orgId): SummaryResult
    {
        $row = Expense::where('organization_id', $orgId)
            ->where('status', ExpenseStatus::Pending)
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(amount), 0) as total')
            ->first();

        return new SummaryResult(
            count: (int) ($row->count ?? 0),
            total: (string) ($row->total ?? '0'),
        );
    }

    /**
     * @return Collection<int, Expense>
     */
    public static function inYear(string $orgId, int $year): Collection
    {
        return Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->select('description', 'amount', 'date')
            ->get();
    }
}
