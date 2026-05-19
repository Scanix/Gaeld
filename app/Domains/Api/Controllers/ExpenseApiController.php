<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Requests\PostExpenseToLedgerRequest;
use App\Domains\Api\Requests\StoreExpenseApiRequest;
use App\Domains\Api\Requests\UpdateExpenseApiRequest;
use App\Domains\Api\Resources\ExpenseResource;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Exceptions\ExpenseLedgerPostingException;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Controller;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * @group Expenses
 *
 * CRUD operations on expenses. Expenses track outgoing payments and costs.
 */
class ExpenseApiController extends Controller
{
    /**
     * List expenses
     *
     * Returns a paginated list of expenses for the current organisation.
     *
     * @queryParam page integer Page number. Example: 1
     * @queryParam search string Search by vendor, category or description. Example: Office
     *
     * @response 200 scenario="Success" {"data":[{"id":"9c8f...","category":"Office supplies","description":"Printer paper","amount":45.90,"vat_amount":3.45,"date":"2025-02-10","vendor":"Digitec","status":"pending","currency":"CHF","supplier_id":null,"created_at":"2025-02-10T08:00:00.000000Z","updated_at":"2025-02-10T08:00:00.000000Z"}],"links":{},"meta":{"current_page":1,"per_page":20,"total":1}}
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = ExpenseQuery::list($request);

        return ExpenseResource::collection($expenses);
    }

    /**
     * Show an expense
     *
     * Returns a single expense by UUID.
     *
     * @urlParam expense string required The expense UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Success" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","category":"Office supplies","description":"Printer paper","amount":45.90,"vat_amount":3.45,"date":"2025-02-10","vendor":"Digitec","status":"pending","currency":"CHF","supplier_id":null,"created_at":"2025-02-10T08:00:00.000000Z","updated_at":"2025-02-10T08:00:00.000000Z"}}
     * @response 404 scenario="Not found" {"message":"Expense not found."}
     */
    public function show(Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense);
    }

    /**
     * Create an expense
     *
     * Records a new expense in the current organisation.
     *
     * @bodyParam category string required The expense category. Example: Office supplies
     * @bodyParam amount number required The expense amount. Example: 45.90
     * @bodyParam date string required The expense date (YYYY-MM-DD). Example: 2025-02-10
     * @bodyParam description string A description of the expense. Example: Printer paper
     * @bodyParam vat_amount number The VAT amount. Example: 3.45
     * @bodyParam vat_rate_id string UUID of a VAT rate. No-example
     * @bodyParam vendor string The vendor name. Example: Digitec
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     *
     * @response 201 scenario="Created" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","category":"Office supplies","description":"Printer paper","amount":45.90,"vat_amount":3.45,"date":"2025-02-10","vendor":"Digitec","status":"pending","currency":"CHF","supplier_id":null,"created_at":"2025-02-10T08:00:00.000000Z","updated_at":"2025-02-10T08:00:00.000000Z"}}
     * @response 422 scenario="Validation error" {"message":"The category field is required.","errors":{"category":["The category field is required."]}}
     */
    public function store(
        StoreExpenseApiRequest $request,
        CreateExpenseAction $action,
        CurrentOrganization $currentOrg,
    ): JsonResponse {
        $this->authorize('create', Expense::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        // Resolve vat_rate_id UUID to internal integer FK
        if (isset($validated['vat_rate_id'])) {
            $validated['vat_rate_id'] = VatRate::where('uuid', $validated['vat_rate_id'])
                ->value('id');
        }

        $dto = CreateExpenseData::fromArray($validated);
        $expense = $action->execute($dto);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * Update an expense
     *
     * Updates an existing expense. Only provided fields are changed.
     *
     * @urlParam expense string required The expense UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @bodyParam category string The expense category. Example: Travel
     * @bodyParam amount number The expense amount. Example: 120.00
     * @bodyParam date string The expense date (YYYY-MM-DD). Example: 2025-02-15
     * @bodyParam description string A description of the expense. Example: Train ticket
     * @bodyParam vat_amount number The VAT amount. Example: 9.23
     * @bodyParam vat_rate_id string UUID of a VAT rate. No-example
     * @bodyParam vendor string The vendor name. Example: SBB
     * @bodyParam currency string ISO 4217 currency code. Example: CHF
     *
     * @response 200 scenario="Updated" {"data":{"id":"9c8f1b2a-3d4e-5f67-8901-abcdef123456","category":"Travel","description":"Train ticket","amount":120.00,"vat_amount":9.23,"date":"2025-02-15","vendor":"SBB","status":"pending","currency":"CHF","supplier_id":null,"created_at":"2025-02-10T08:00:00.000000Z","updated_at":"2025-02-20T14:30:00.000000Z"}}
     */
    public function update(
        UpdateExpenseApiRequest $request,
        Expense $expense,
        UpdateExpenseAction $action,
    ): ExpenseResource {
        $this->authorize('update', $expense);

        $validated = $this->completeUpdatePayload($expense, $request->validated());

        // Resolve vat_rate_id UUID to internal integer FK
        if (isset($validated['vat_rate_id'])) {
            $validated['vat_rate_id'] = VatRate::where('uuid', $validated['vat_rate_id'])
                ->value('id');
        }

        $dto = UpdateExpenseData::fromArray($validated);
        $action->execute($expense, $dto);

        return new ExpenseResource($expense->fresh());
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    private function completeUpdatePayload(Expense $expense, array $validated): array
    {
        return [
            'category' => $validated['category'] ?? $expense->category,
            'amount' => $validated['amount'] ?? $expense->amount,
            'date' => $validated['date'] ?? $expense->date->toDateString(),
            'description' => array_key_exists('description', $validated) ? $validated['description'] : $expense->description,
            'vat_amount' => array_key_exists('vat_amount', $validated) ? $validated['vat_amount'] : $expense->vat_amount,
            'vat_rate_id' => array_key_exists('vat_rate_id', $validated) ? $validated['vat_rate_id'] : $expense->vat_rate_id,
            'vendor' => array_key_exists('vendor', $validated) ? $validated['vendor'] : $expense->vendor,
            'currency' => array_key_exists('currency', $validated) ? $validated['currency'] : $expense->currency,
            'supplier_id' => $expense->supplier_id,
            'payment_method' => $expense->payment_method,
            'expense_account_code' => $expense->expense_account_code,
            'bank_account_code' => $expense->bank_account_code,
            'receipt_path' => $expense->receipt_path,
        ];
    }

    /**
     * Delete an expense
     *
     * Permanently deletes an expense.
     *
     * @urlParam expense string required The expense UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 204 scenario="Deleted"
     * @response 404 scenario="Not found" {"message":"Expense not found."}
     */
    public function destroy(Expense $expense, DeleteExpenseAction $action): JsonResponse
    {
        $this->authorize('delete', $expense);

        $action->execute($expense);

        return response()->json(null, 204);
    }

    /**
     * Approve expense
     *
     * Approves a pending expense.
     *
     * @urlParam expense string required The expense UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @response 200 scenario="Approved" {"data":{"id":"9c8f...","status":"approved"}}
     * @response 422 scenario="Invalid state" {"message":"Expense cannot be approved in its current state."}
     */
    public function approve(
        Expense $expense,
        ApproveExpenseAction $action,
        DashboardService $dashboardService,
    ): ExpenseResource|JsonResponse {
        $this->authorize('update', $expense);

        try {
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        $dashboardService->flushCache($expense->organization_id);

        return new ExpenseResource($expense->fresh());
    }

    /**
     * Post expense to ledger
     *
     * Posts an approved expense to the accounting ledger.
     *
     * @urlParam expense string required The expense UUID. Example: 9c8f1b2a-3d4e-5f67-8901-abcdef123456
     *
     * @bodyParam expense_account_code string required The expense account code. Example: 6000
     * @bodyParam bank_account_code string Optional bank account code (defaults to 1020). Example: 1020
     *
     * @response 200 scenario="Posted" {"data":{"id":"9c8f...","status":"posted"}}
     * @response 422 scenario="Invalid state" {"message":"Expense cannot be posted in its current state."}
     */
    public function postToLedger(
        PostExpenseToLedgerRequest $request,
        Expense $expense,
        PostExpenseAction $action,
        DashboardService $dashboardService,
    ): ExpenseResource|JsonResponse {
        $this->authorize('update', $expense);

        $validated = $request->validated();

        try {
            $action->execute(
                $expense,
                $validated['expense_account_code'],
                $validated['bank_account_code'] ?? AccountCode::BANK_CASH,
            );
        } catch (InvalidExpenseStateException|ExpenseLedgerPostingException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (ModelNotFoundException) {
            return response()->json(['message' => 'Account not found.'], 404);
        }

        $dashboardService->flushCache($expense->organization_id);

        return new ExpenseResource($expense->fresh());
    }
}
