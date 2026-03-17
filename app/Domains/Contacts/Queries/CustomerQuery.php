<?php

namespace App\Domains\Contacts\Queries;

use App\Domains\Contacts\Models\Customer;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class CustomerQuery
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Customer::query(), $request)
            ->allowedSorts(['name', 'email', 'city', 'country', 'created_at'], 'name', 'asc')
            ->allowedFilters(['country', 'currency'])
            ->searchable(['name', 'email', 'city', 'vat_number'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }
}
