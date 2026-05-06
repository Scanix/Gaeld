<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Queries\AccountQuery;
use App\Domains\Accounting\Queries\VatRateQuery;
use App\Domains\Banking\Queries\BankAccountQuery;
use App\Domains\Contacts\Queries\ContactQuery;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Notifications\ExpenseSubmittedNotification;
use App\Domains\Expenses\Queries\ExpenseCategoryQuery;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Expenses\Requests\StoreExpenseRequest;
use App\Domains\Expenses\Requests\UpdateExpenseRequest;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Services\DashboardService;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Permission\PermissionRegistrar;

/**
 * Expense CRUD operations.
 */
class ExpenseController extends Controller
{
    use HandlesFlashErrorResponses;

    public function __construct(
        private FileUploadService $uploadService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Expense::class);

        return Inertia::render('Expenses/Index', [
            'expenses' => ExpenseQuery::list($request),
            'query' => [
                'sort' => $request->input('sort', 'date'),
                'direction' => $request->input('direction', 'desc'),
                'search' => $request->input('search', ''),
                'filter' => $request->input('filter', []),
            ],
        ]);
    }

    public function create(Request $request): Response
    {
        $this->authorize('create', Expense::class);

        $ocrData = null;
        if ($request->filled('scan_id')) {
            $scan = ReceiptScan::where('scan_id', $request->input('scan_id'))
                ->where('user_id', $request->user()->id)
                ->whereIn('status', ['pending', 'completed'])
                ->first();

            if ($scan) {
                $ocrData = array_merge(
                    ['scan_id' => $scan->scan_id, 'receipt_path' => $scan->receipt_path],
                    is_array($scan->extracted_data) ? $scan->extracted_data : [],
                );
            }
        }

        return Inertia::render('Expenses/Create', [
            'vatRates' => VatRateQuery::active(),
            'suppliers' => ContactQuery::forSelect(),
            'categories' => ExpenseCategoryQuery::forSelect(),
            'expenseAccounts' => AccountQuery::forSelect(AccountType::Expense),
            'bankAccounts' => BankAccountQuery::forSelect(),
            'ocrData' => $ocrData,
        ]);
    }

    public function store(StoreExpenseRequest $request, CreateExpenseAction $action, CurrentOrganization $currentOrg): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt')) {
            $validated['receipt_path'] = $this->uploadService->store(
                $request->file('receipt'),
                "receipts/{$currentOrg->id()}",
            );
        } elseif ($request->filled('receipt_path')) {
            // Accept a pre-stored receipt path from the scan-receipt flow
            $path = $request->input('receipt_path');
            if (str_starts_with($path, "receipts/{$currentOrg->id()}/") && Storage::disk('local')->exists($path)) {
                $validated['receipt_path'] = $path;
            } else {
                $validated['receipt_path'] = null;
            }
        }
        $validated['organization_id'] = $currentOrg->id();
        $validated['user_id'] = $request->user()->id;

        $expense = $action->execute(CreateExpenseData::fromArray($validated));

        if ($request->filled('scan_id')) {
            ReceiptScan::where('scan_id', $request->input('scan_id'))
                ->where('organization_id', $currentOrg->id())
                ->whereIn('status', ['pending', 'completed'])
                ->update(['status' => 'validated']);
        }

        app(DashboardService::class)->flushCache($currentOrg->id());

        // Notify users with expense approval permission
        app()[PermissionRegistrar::class]->setPermissionsTeamId($currentOrg->id());
        $approvers = $currentOrg->get()->users()
            ->permission(Permission::ExpensesApprove->value)
            ->where('users.id', '!=', $request->user()->id)
            ->get();

        Notification::send($approvers, new ExpenseSubmittedNotification($expense, $request->user()->name));

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_created'));
    }

    public function show(Expense $expense): Response
    {
        $this->authorize('view', $expense);

        return Inertia::render('Expenses/Show', [
            'expense' => $expense->load(['vatRate', 'supplier', 'journalEntry.lines.account']),
            'receiptUrl' => $expense->receipt_path ? route('expenses.receipt.download', $expense) : null,
        ]);
    }

    public function edit(Request $request, Expense $expense): Response
    {
        $this->authorize('update', $expense);

        return Inertia::render('Expenses/Edit', [
            'expense' => $expense->load('vatRate'),
            'vatRates' => VatRateQuery::active(),
            'suppliers' => ContactQuery::forSelect(),
            'categories' => ExpenseCategoryQuery::forSelect(),
            'expenseAccounts' => AccountQuery::forSelect(AccountType::Expense),
            'bankAccounts' => BankAccountQuery::forSelect(),
            'receiptUrl' => $expense->receipt_path ? route('expenses.receipt.download', $expense) : null,
        ]);
    }

    public function update(UpdateExpenseRequest $request, Expense $expense, UpdateExpenseAction $action): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('receipt')) {
            $this->uploadService->delete($expense->receipt_path);
            $validated['receipt_path'] = $this->uploadService->store(
                $request->file('receipt'),
                "receipts/{$expense->organization_id}",
            );
        }

        try {
            $action->execute($expense, UpdateExpenseData::fromArray($validated));
        } catch (InvalidExpenseStateException $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_updated'));
    }

    public function destroy(Expense $expense, DeleteExpenseAction $action): RedirectResponse
    {
        $this->authorize('delete', $expense);

        $orgId = $expense->organization_id;

        try {
            $this->uploadService->delete($expense->receipt_path);
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return $this->backWithError($e);
        }

        app(DashboardService::class)->flushCache($orgId);

        return redirect()->route('expenses.index')
            ->with('success', __('app.expense_deleted'));
    }
}
