<?php

namespace App\Domains\Invoicing\Queries;

use App\Domains\Invoicing\Models\Invoice;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class InvoiceQuery
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Invoice::with(['customer']), $request)
            ->allowedSorts(['issue_date', 'due_date', 'total', 'number', 'status'], 'issue_date', 'desc')
            ->allowedFilters(['status'])
            ->searchable(['number', 'customer.name'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
