<?php

namespace App\Domains\Invoicing\Queries;

use App\Domains\Invoicing\Models\Invoice;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class InvoiceQuery
{
    /**
     * @return LengthAwarePaginator<int, Invoice>
     */
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Invoice::with(['customer', 'lines', 'payments'])->withSum('payments', 'amount'), $request)
            ->allowedSorts(['issue_date', 'due_date', 'total', 'number', 'status'], 'issue_date', 'desc')
            ->allowedFilters(['status', 'type'])
            ->searchable(['number', 'customer.name'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
