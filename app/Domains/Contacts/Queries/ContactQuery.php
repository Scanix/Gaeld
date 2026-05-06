<?php

namespace App\Domains\Contacts\Queries;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class ContactQuery
{
    /**
     * @return LengthAwarePaginator<int, Contact>
     */
    public static function list(Request $request, int $perPage = 20): LengthAwarePaginator
    {
        return QueryBuilder::for(Contact::query(), $request)
            ->allowedSorts(['name', 'email', 'city', 'country', 'created_at'], 'name', 'asc')
            ->allowedFilters(['country', 'currency', 'default_expense_category'])
            ->searchable(['name', 'email', 'city', 'vat_number', 'contactPersons.first_name', 'contactPersons.last_name', 'contactPersons.email'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, Contact>
     */
    public static function forSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:contacts"])->remember(
            "contacts_select:{$orgId}",
            600,
            fn () => Contact::orderBy('name')->get()
        );
    }
}
