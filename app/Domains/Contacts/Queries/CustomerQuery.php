<?php

namespace App\Domains\Contacts\Queries;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class CustomerQuery
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Customer::query(), $request)
            ->allowedSorts(['name', 'email', 'city', 'country', 'created_at'], 'name', 'asc')
            ->allowedFilters(['country', 'currency'])
            ->searchable(['name', 'email', 'city', 'vat_number', 'contactPersons.first_name', 'contactPersons.last_name', 'contactPersons.email'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function forSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:contacts"])->remember(
            "customers_select:{$orgId}",
            600,
            fn () => Customer::orderBy('name')->get()
        );
    }
}
