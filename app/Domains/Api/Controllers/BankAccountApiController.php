<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Requests\StoreBankAccountApiRequest;
use App\Domains\Api\Resources\BankAccountResource;
use App\Domains\Api\Services\AccountCodeResolver;
use App\Domains\Banking\Actions\CreateBankAccountAction;
use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Bank Accounts
 *
 * Manage bank accounts and their balances.
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
            ->with('ledgerAccount')
            ->when($request->boolean('active_only'), fn ($q) => $q->where('is_active', true))
            ->orderBy('name')
            ->paginate(min((int) $request->input('per_page', 25), 100));

        return BankAccountResource::collection($bankAccounts);
    }

    /**
     * Show a bank account
     *
     * Returns a single bank account by UUID.
     *
     * @urlParam bankAccount string required The bank account UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","name":"UBS Business","iban":"CH93 0076 2011 6238 5295 7","bank_name":"UBS","currency":"CHF","balance":12500.00,"is_active":true,"account_id":"abc123","created_at":"2025-01-01T00:00:00.000000Z","updated_at":"2025-01-15T10:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Bank account not found."}
     */
    public function show(BankAccount $bankAccount, CurrentOrganization $currentOrganization): BankAccountResource
    {
        abort_unless($bankAccount->organization_id === $currentOrganization->id(), 404);
        $this->authorize('view', $bankAccount);

        return new BankAccountResource($bankAccount->load('ledgerAccount'));
    }

    /**
     * Create a bank account for the current organization.
     *
     * @bodyParam name string required Display name. Example: PostFinance
     * @bodyParam iban string Swiss IBAN. Example: CH9300762011623852957
     * @bodyParam qr_iban string Swiss QR-IBAN. Example: CH4431999123000889012
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     * @bodyParam account_code string required Active GL account code. Example: 1020
     * @bodyParam bank_name string Bank name. Example: PostFinance
     * @bodyParam bic string BIC/SWIFT code. Example: POFICHBEXXX
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f...","name":"PostFinance","iban":"CH9300762011623852957","qr_iban":"CH4431999123000889012","currency":"CHF","account_id":"abc123","account_code":"1020","is_active":true}}
     */
    public function store(
        StoreBankAccountApiRequest $request,
        CurrentOrganization $currentOrganization,
        AccountCodeResolver $accountResolver,
        CreateBankAccountAction $action,
    ): JsonResponse {
        $this->authorize('create', BankAccount::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrganization->id();

        if (isset($validated['account_code'])) {
            $validated['account_id'] = (string) $accountResolver
                ->resolve($currentOrganization->id(), $validated['account_code'])
                ->id;
            unset($validated['account_code']);
        }

        $bankAccount = $action->execute(CreateBankAccountData::fromArray($validated));

        return (new BankAccountResource($bankAccount))
            ->response()
            ->setStatusCode(201);
    }
}
