<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Api\Requests\StoreExpenseApiRequest;
use App\Domains\Api\Requests\UpdateExpenseApiRequest;
use App\Domains\Api\Resources\ExpenseResource;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
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
                ->where('organization_id', $currentOrg->id())
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

        $validated = $request->validated();

        // Resolve vat_rate_id UUID to internal integer FK
        if (isset($validated['vat_rate_id'])) {
            $validated['vat_rate_id'] = VatRate::where('uuid', $validated['vat_rate_id'])
                ->where('organization_id', $expense->organization_id)
                ->value('id');
        }

        $dto = UpdateExpenseData::fromArray($validated);
        $action->execute($expense, $dto);

        return new ExpenseResource($expense->fresh());
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
}
