<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Resources\AccountResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API: chart-of-accounts (read-only listing and detail).
 */
class AccountApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->paginate($request->input('per_page', 50));

        return AccountResource::collection($accounts);
    }

    public function show(Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }
}
