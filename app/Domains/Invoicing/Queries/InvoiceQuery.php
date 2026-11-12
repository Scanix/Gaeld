<?php

namespace App\Domains\Invoicing\Queries;

use App\Domains\Invoicing\Models\Invoice;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class InvoiceQuery
{
    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return self::filtered($request)->paginate($perPage)->withQueryString();
    }

    /** @return Builder<Invoice> */
    public static function filtered(Request $request): Builder
    {
        Validator::make($request->only(['from', 'to', 'due_before', 'customer_id']), [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d'],
            'due_before' => ['nullable', 'date_format:Y-m-d'],
            'customer_id' => ['nullable', 'uuid'],
        ])->validate();

        $query = Invoice::with(['customer', 'lines.vatRate', 'payments'])->withSum('payments', 'amount');

        QueryBuilder::for($query, $request)
            ->allowedSorts(['issue_date', 'due_date', 'total', 'number', 'status'], 'issue_date', 'desc')
            ->allowedFilters(['status', 'type'])
            ->searchable(['number', 'customer.name'])
            ->apply();

        return $query
            ->when($request->filled('from'), fn (Builder $query) => $query->where('issue_date', '>=', $request->input('from')))
            ->when($request->filled('to'), fn (Builder $query) => $query->where('issue_date', '<=', $request->input('to')))
            ->when($request->filled('due_before'), fn (Builder $query) => $query->where('due_date', '<=', $request->input('due_before')))
            ->when($request->filled('customer_id'), fn (Builder $query) => $query->whereHas('customer', fn (Builder $customer) => $customer->where('uuid', $request->input('customer_id'))));
    }
}
