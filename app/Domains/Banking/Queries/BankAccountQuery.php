<?php

namespace App\Domains\Banking\Queries;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class BankAccountQuery
{
    /**
     * @return LengthAwarePaginator<int, BankAccount>
     */
    public static function list(Request $request, int $perPage = 25): LengthAwarePaginator
    {
        return QueryBuilder::for(
            BankAccount::query()->with('ledgerAccount'),
            $request,
        )
            ->allowedSorts(['name', 'iban', 'currency', 'created_at'], 'name', 'asc')
            ->searchable(['name', 'iban'])
            ->apply()
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @return Collection<int, BankAccount>
     */
    public static function forSelect(): Collection
    {
        $orgId = app(CurrentOrganization::class)->id();

        return Cache::tags(["org:{$orgId}:banking"])->remember(
            "bank_accounts_select:{$orgId}",
            600,
            fn () => BankAccount::where('organization_id', $orgId)
                ->where('is_active', true)
                ->select('id', 'account_id', 'name', 'iban', 'currency')
                ->with('ledgerAccount:id,code')
                ->orderBy('name')
                ->get()
        );
    }
}
