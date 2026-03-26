<?php

namespace App\Domains\Api\Controllers;

use App\Domains\Api\Resources\ExpenseResource;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use App\Http\Controllers\Controller;
use Illuminate\Validation\Rule;

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
        Request $request,
        CreateExpenseAction $action,
        CurrentOrganization $currentOrg,
    ): JsonResponse {
        $this->authorize('create', Expense::class);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $currentOrg->id()),
            ],
            'vendor' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
        ]);
        $validated['organization_id'] = $currentOrg->id();

        $dto = CreateExpenseData::fromArray($validated);
        $expense = $action->execute($dto);

        return (new ExpenseResource($expense))
            ->response()
            ->setStatusCode(201);
    }

    public function update(
        Request $request,
        Expense $expense,
        UpdateExpenseAction $action,
        CurrentOrganization $currentOrg,
    ): ExpenseResource {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'category' => 'required|string|max:100',
            'amount' => 'required|numeric|min:0.01',
            'date' => 'required|date',
            'description' => 'nullable|string',
            'vat_amount' => 'nullable|numeric|min:0',
            'vat_rate_id' => [
                'nullable',
                Rule::exists('vat_rates', 'id')->where('organization_id', $currentOrg->id()),
            ],
            'vendor' => 'nullable|string|max:255',
            'currency' => 'nullable|string|size:3',
        ]);

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
