<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Accounting\Queries\VatRateQuery;
use App\Domains\Contacts\Queries\SupplierQuery;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\DeleteExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\Actions\UpdateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\DTOs\UpdateExpenseData;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Queries\ExpenseQuery;
use App\Domains\Expenses\Requests\ScanReceiptRequest;
use App\Domains\Expenses\Requests\StoreExpenseRequest;
use App\Domains\Expenses\Requests\UpdateExpenseRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Support\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExpenseController extends Controller
{
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

        return Inertia::render('Expenses/Create', [
            'vatRates' => VatRateQuery::active(),
            'suppliers' => SupplierQuery::forSelect(),
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
            }
        }
        $validated['organization_id'] = $currentOrg->id();

        $expense = $action->execute(CreateExpenseData::fromArray($validated));

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
            'suppliers' => SupplierQuery::forSelect(),
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
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_updated'));
    }

    public function destroy(Expense $expense, DeleteExpenseAction $action): RedirectResponse
    {
        $this->authorize('delete', $expense);

        try {
            $this->uploadService->delete($expense->receipt_path);
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.index')
            ->with('success', __('app.expense_deleted'));
    }

    public function approve(Expense $expense, ApproveExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        try {
            $action->execute($expense);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_approved'));
    }

    public function postToLedger(Expense $expense, Request $request, PostExpenseAction $action): RedirectResponse
    {
        $this->authorize('update', $expense);

        $validated = $request->validate([
            'expense_account_code' => 'required|string',
        ]);

        try {
            $action->execute($expense, $validated['expense_account_code']);
        } catch (InvalidExpenseStateException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.expense_posted'));
    }

    public function removeReceipt(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if ($expense->receipt_path) {
            $this->uploadService->delete($expense->receipt_path);
            $expense->update(['receipt_path' => null]);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.receipt_removed'));
    }

    public function downloadReceipt(Expense $expense): StreamedResponse
    {
        $this->authorize('view', $expense);

        if (! $expense->receipt_path || ! Storage::disk('local')->exists($expense->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $expense->receipt_path,
            basename($expense->receipt_path),
        );
    }

    public function scanReceipt(ScanReceiptRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $receiptPath = $this->uploadService->store($request->file('receipt'), "receipts/{$currentOrg->id()}");
        $scanId = Str::uuid()->toString();

        Cache::put("receipt_scan:{$scanId}", [
            'status' => 'processing',
            'receipt_path' => $receiptPath,
            'extracted' => null,
        ], now()->addMinutes(30));

        ProcessReceiptOcrJob::dispatch($scanId, $receiptPath);

        return response()->json([
            'scan_id' => $scanId,
            'receipt_path' => $receiptPath,
        ]);
    }

    public function scanReceiptStatus(Request $request, string $scanId): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $data = Cache::get("receipt_scan:{$scanId}");

        if (! $data) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($data);
    }
}
