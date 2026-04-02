<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Resources\BankAccountResource;
use App\Domains\Banking\Models\BankAccount;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bank Accounts
 *
 * Read-only access to bank accounts and their balances.
 */
class BankAccountApiController extends Controller
{
    /**
     * List bank accounts
     *
     * Returns a paginated list of bank accounts.
     *
     * @queryParam active_only boolean Only return active bank accounts. Example: true
     * @queryParam per_page integer Number of results per page (max 100). Example: 25
     * @queryParam page integer Page number. Example: 1
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","name":"UBS Business","iban":"CH93 0076 2011 6238 5295 7","bank_name":"UBS","currency":"CHF","balance":12500.00,"is_active":true,"account_id":"abc123","created_at":"2025-01-01T00:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"per_page":25,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::query()
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate($request->input('per_page', 25));

        return BankAccountResource::collection($bankAccounts);
    }

    /**
     * Show a bank account
     *
     * Returns a single bank account by UUID.
     *
     * @urlParam bank_account string required The bank account UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","name":"UBS Business","iban":"CH93 0076 2011 6238 5295 7","bank_name":"UBS","currency":"CHF","balance":12500.00,"is_active":true,"account_id":"abc123","created_at":"2025-01-01T00:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Bank account not found."}
     */
    public function show(BankAccount $bankAccount): BankAccountResource
    {
        $this->authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount);
    }
}
