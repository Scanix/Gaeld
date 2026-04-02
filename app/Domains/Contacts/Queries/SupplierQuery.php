<?php

namespace App\Domains\Contacts\Queries;

use App\Domains\Contacts\Models\Supplier;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class SupplierQuery
{
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Supplier::query(), $request)
            ->allowedSorts(['name', 'email', 'city', 'country', 'created_at'], 'name', 'asc')
            ->allowedFilters(['country', 'currency', 'default_expense_category'])
            ->searchable(['name', 'email', 'city', 'vat_number', 'default_expense_category'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }

    public static function forSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:contacts"])->remember(
            "suppliers_select:{$orgId}",
            600,
            fn () => Supplier::orderBy('name')->get()
        );
    }

    public static function hasMatchingSupplier(string $organizationId, string $creditorName): bool
    {
        return Supplier::where('organization_id', $organizationId)
            ->whereNotNull('default_expense_category')
            ->where('name', 'ilike', '%'.$creditorName.'%')
            ->exists();
    }

    public static function findByCreditorName(string $organizationId, string $creditorName): ?Supplier
    {
        return Supplier::where('organization_id', $organizationId)
            ->whereNotNull('default_expense_category')
            ->where('name', 'ilike', '%'.$creditorName.'%')
            ->first();
    }
}
