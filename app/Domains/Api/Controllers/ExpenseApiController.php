<?php

namespace App\Domains\Api\Controllers;

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

class ExpenseApiController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Expense::class);

        $expenses = ExpenseQuery::list($request);

        return ExpenseResource::collection($expenses);
    }

    public function show(Expense $expense): ExpenseResource
    {
        $this->authorize('view', $expense);

        return new ExpenseResource($expense);
    }

    public function store(
        StoreExpenseApiRequest $request,
        CreateExpenseAction $action,
        CurrentOrganization $currentOrg,
    ): JsonResponse {
        $this->authorize('create', Expense::class);

        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $dto = CreateExpenseData::fromArray($validated);
        $expense = $action->execute($dto);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        UpdateExpenseApiRequest $request,
        Expense $expense,
        UpdateExpenseAction $action,
    ): ExpenseResource {
        $this->authorize('update', $expense);

        $validated = $request->validated();

        $dto = UpdateExpenseData::fromArray($validated);
        $action->execute($expense, $dto);

        return new ExpenseResource($expense->fresh());
    }

    public function destroy(Expense $expense, DeleteExpenseAction $action): JsonResponse
    {
        $this->authorize('delete', $expense);

        $action->execute($expense);

        return response()->json(null, 204);
    }
}
