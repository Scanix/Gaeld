<?php

namespace App\Domains\Banking\Queries;

use App\Domains\Banking\Models\BankAccount;
use App\Support\QueryBuilder;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;

class BankAccountQuery
{
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
}
