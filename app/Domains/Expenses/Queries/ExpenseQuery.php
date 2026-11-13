<?php

namespace App\Domains\Expenses\Queries;

use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Organizations\Enums\Permission;
use App\Support\DTOs\SummaryResult;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Validator;

class ExpenseQuery
{
    /**
     * @return LengthAwarePaginator<int, Expense>
     */
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return self::filtered($request)
            ->paginate($perPage)
            ->withQueryString()
            ->through(function ($expense) {
                if (empty($expense->vendor)
                    && $expense->relationLoaded('supplier')
                    && $expense->supplier) {
                    $expense->vendor = $expense->supplier->name;
                }

                return $expense;
            });
    }

    /** @return Builder<Expense> */
    public static function filtered(Request $request): Builder
    {
        Validator::make($request->only(['from', 'to', 'supplier_id']), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'supplier_id' => ['nullable', 'uuid'],
        ])->validate();

        $selfService = $request->user()->hasPermissionTo(Permission::ExpensesViewOwn)
            && ! $request->user()->hasPermissionTo(Permission::ExpensesView);
        $query = Expense::query()->when(! $selfService, fn ($query) => $query->with('supplier'));

        if ($selfService) {
            $query->where('user_id', $request->user()->id);
        }

        QueryBuilder::for($query, $request)
            ->allowedSorts(['date', 'amount', 'category', 'vendor', 'status'], 'date', 'desc')
            ->allowedFilters(['status', 'category'])
            ->searchable(['description', 'vendor', 'category'])
            ->apply();

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->where('date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('date', '<=', $request->input('to')))
            ->when($request->filled('supplier_id'), fn (Builder $query) => $query->whereHas('supplier', fn (Builder $supplier) => $supplier->where('uuid', $request->input('supplier_id'))));
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
