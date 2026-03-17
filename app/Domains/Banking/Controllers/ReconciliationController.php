<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Services\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReconciliationController extends Controller
{
    public function __construct(
        private BankImportService $importService,
        private ReconciliationService $reconciliationService,
    ) {}

    /**
     * Reconciliation dashboard — list bank accounts and unreconciled transactions.
     */
    public function index(): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::with('ledgerAccount')
            ->withCount([
                'transactions as unreconciled_count' => fn ($q) => $q->where('is_reconciled', false),
            ])
            ->orderBy('name')
            ->get();

        return Inertia::render('Banking/Reconciliation', [
            'bankAccounts' => $bankAccounts,
            'features' => [
                'auto_reconciliation' => FeatureFlag::enabled('auto_reconciliation'),
            ],
        ]);
    }

    /**
     * Show transactions for a bank account with reconciliation UI.
     */
    public function show(BankAccount $bankAccount, Request $request): Response
    {
        $this->authorize('view', $bankAccount);

        $filter = $request->input('filter', 'unreconciled');

        $transactionsQuery = $bankAccount->transactions()
            ->with(['matchedInvoice.client', 'matchedExpense', 'journalEntry'])
            ->orderByDesc('date');

        if ($filter === 'unreconciled') {
            $transactionsQuery->where('is_reconciled', false);
        } elseif ($filter === 'reconciled') {
            $transactionsQuery->where('is_reconciled', true);
        }

        $transactions = $transactionsQuery->paginate(30);

        $suggestions = $this->reconciliationService->getSuggestionsForTransactions($transactions->items());

        return Inertia::render('Banking/ReconciliationShow', [
            'bankAccount' => $bankAccount->load('ledgerAccount'),
            'transactions' => $transactions,
            'suggestions' => $suggestions,
            'filter' => $filter,
            'features' => [
                'auto_reconciliation' => FeatureFlag::enabled('auto_reconciliation'),
            ],
        ]);
    }

    /**
     * Upload and import a CAMT file.
     */
    public function import(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $request->validate([
            'camt_file' => 'required|file|max:10240', // 10MB max
        ]);

        $file = $request->file('camt_file');
        $xml = file_get_contents($file->getRealPath());
        if ($xml === false) {
            return redirect()->back()->with('error', 'Could not read the uploaded file.');
        }
        $filename = $file->getClientOriginalName();

        try {
            $import = $this->importService->importCamtFile($bankAccount, $xml, $filename);

            return redirect()->route('reconciliation.show', $bankAccount)
                ->with('success', "{$import->transaction_count} transactions imported from {$filename}.");
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reconcile a transaction with an invoice.
     */
    public function reconcileInvoice(Request $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validate([
            'invoice_id' => 'required|uuid|exists:invoices,id',
        ]);

        $invoice = Invoice::where('organization_id', $bankAccount->organization_id)
            ->findOrFail($validated['invoice_id']);

        try {
            $this->reconciliationService->reconcileWithInvoice($transaction, $invoice);

            return redirect()->back()
                ->with('success', "Transaction reconciled with invoice {$invoice->number}.");
        } catch (\DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reconcile a transaction with an expense.
     */
    public function reconcileExpense(Request $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validate([
            'expense_id' => 'required|uuid|exists:expenses,id',
            'expense_account_code' => 'required|string|max:10',
        ]);

        $expense = Expense::where('organization_id', $bankAccount->organization_id)
            ->findOrFail($validated['expense_id']);

        try {
            $this->reconciliationService->reconcileWithExpense(
                $transaction,
                $expense,
                $validated['expense_account_code'],
            );

            return redirect()->back()
                ->with('success', 'Transaction reconciled with expense.');
        } catch (\DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Manually reconcile a transaction with a contra account.
     */
    public function reconcileManual(Request $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validate([
            'contra_account_code' => 'required|string|max:10',
        ]);

        try {
            $this->reconciliationService->reconcileManual(
                $transaction,
                $validated['contra_account_code'],
            );

            return redirect()->back()
                ->with('success', 'Transaction reconciled.');
        } catch (\DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Confirm a match: reconcile + record payment in one click.
     */
    public function confirmMatch(BankMatch $match): RedirectResponse
    {
        $bankAccount = $match->bankTransaction->bankAccount;
        $this->authorize('update', $bankAccount);

        try {
            $this->reconciliationService->confirmMatch($match);

            return redirect()->back()
                ->with('success', "Match confirmed and payment recorded for invoice {$match->invoice->number}.");
        } catch (\DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Auto-reconcile all unreconciled transactions (EE only).
     */
    public function autoReconcile(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        try {
            $result = $this->reconciliationService->autoReconcile($bankAccount);

            return redirect()->back()
                ->with('success', "Auto-reconciliation complete: {$result['matched']} matched, {$result['unmatched']} unmatched.");
        } catch (\DomainException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }
}
