<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Api\Resources\AccountResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Accounts
 *
 * Read-only access to the chart of accounts.
 */
class AccountApiController extends Controller
{
    /**
     * List accounts
     *
     * Returns a paginated list of accounts from the chart of accounts.
     *
     * @queryParam type string Filter by account type (asset, liability, equity, revenue, expense). Example: asset
     * @queryParam active_only boolean Only return active accounts. Example: true
     * @queryParam per_page integer Number of results per page (max 100). Example: 50
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","code":"1000","name":"Cash","type":"asset","parent_id":null,"is_active":true,"description":"Liquid funds"}],"links":{},"meta":{"current_page":1,"per_page":50,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::query()
            ->with('parent')
            ->when($request->input('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('code')
            ->paginate($request->input('per_page', 50));

        return AccountResource::collection($accounts);
    }

    /**
     * Show an account
     *
     * Returns a single account by UUID.
     *
     * @urlParam account string required The account UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","code":"1000","name":"Cash","type":"asset","parent_id":null,"is_active":true,"description":"Liquid funds"}}
     * @response 404 scenario="Not found" {"message":"Account not found."}
     */
    public function show(Account $account): AccountResource
    {
        $this->authorize('view', $account);

        return new AccountResource($account);
    }
}
