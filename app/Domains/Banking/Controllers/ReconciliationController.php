<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\ReconciliationFailedException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Requests\ImportCamtRequest;
use App\Domains\Banking\Requests\ReconcileExpenseRequest;
use App\Domains\Banking\Requests\ReconcileInvoiceRequest;
use App\Domains\Banking\Requests\ReconcileManualRequest;
use App\Domains\Banking\Services\BankImportService;
use App\Domains\Banking\Services\PersonalPatternService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Banking\Services\SuggestionService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Http\Controllers\Controller;
use App\Support\Exceptions\FeatureDisabledException;
use App\Support\FeatureFlag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank reconciliation: matching transactions to invoices/expenses
 * and confirming or rejecting suggested matches.
 */
class ReconciliationController extends Controller
{
    public function __construct(
        private BankImportService $importService,
        private ReconciliationService $reconciliationService,
        private SuggestionService $suggestionService,
        private PersonalPatternService $personalPatternService,
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
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Banking/Reconciliation', [
            'bankAccounts' => $bankAccounts,
            'pageFeatures' => [
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
            ->with(['bankAccount', 'matchedInvoice.customer', 'matchedExpense', 'journalEntry'])
            ->orderByDesc('date');

        if ($filter === 'unreconciled') {
            $transactionsQuery->where('is_reconciled', false);
        } elseif ($filter === 'reconciled') {
            $transactionsQuery->where('is_reconciled', true);
        }

        $transactions = $transactionsQuery->paginate(config('accounting.pagination.reconciliation'));

        // Only generate suggestions for unreconciled transactions on the current page
        // to avoid N+1 query storms across already-reconciled items
        $unreconciledOnPage = collect($transactions->items())
            ->filter(fn (BankTransaction $t) => ! $t->is_reconciled);

        $suggestions = $unreconciledOnPage->isNotEmpty()
            ? $this->suggestionService->generateSuggestionsForTransactions($unreconciledOnPage)
            : [];

        // For mixed-use accounts, check which unreconciled transactions
        // match known personal counterparty patterns
        $personalSuggestions = [];
        if ($bankAccount->is_mixed_use && $unreconciledOnPage->isNotEmpty()) {
            /** @var BankTransaction $tx */
            foreach ($unreconciledOnPage as $tx) {
                if ($this->personalPatternService->isLikelyPersonal($tx, $bankAccount->organization_id)) {
                    $personalSuggestions[] = $tx->id;
                }
            }
        }

        // Fetch open invoices for the searchable reconciliation selector
        $openInvoices = Invoice::where('organization_id', $bankAccount->organization_id)
            ->open()
            ->with('customer:id,name')
            ->orderByDesc('issue_date')
            ->get(['id', 'number', 'total', 'currency', 'customer_id', 'issue_date', 'status']);

        return Inertia::render('Banking/ReconciliationShow', [
            'bankAccount' => $bankAccount->load('ledgerAccount'),
            'transactions' => $transactions,
            'suggestions' => $suggestions,
            'personalSuggestions' => $personalSuggestions,
            'filter' => $filter,
            'openInvoices' => $openInvoices,
            'pageFeatures' => [
                'auto_reconciliation' => FeatureFlag::enabled('auto_reconciliation'),
            ],
        ]);
    }

    /**
     * Upload and import a CAMT file.
     */
    public function import(ImportCamtRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $file = $request->file('camt_file');
        $content = file_get_contents($file->getRealPath());
        if ($content === false) {
            return redirect()->back()->with('error', 'Could not read the uploaded file.');
        }
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($file->getClientOriginalName()));
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        try {
            if ($extension === 'csv') {
                $mapping = $request->validated('csv_mapping', []);
                $delimiter = $request->validated('csv_delimiter', ',');
                $import = $this->importService->importCsvFile($bankAccount, $content, $filename, $mapping, $delimiter);
            } elseif (in_array($extension, ['sta', 'mt940', 'mt9', 'fin', 'swi'])) {
                $import = $this->importService->importMt940File($bankAccount, $content, $filename);
            } else {
                $import = $this->importService->importCamtFile($bankAccount, $content, $filename);
            }

            return redirect()->route('reconciliation.show', $bankAccount)
                ->with('success', __('app.transactions_imported', ['count' => $import->transaction_count, 'filename' => $filename]));
        } catch (\InvalidArgumentException|\RuntimeException $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    /**
     * Reconcile a transaction with an invoice.
     */
    public function reconcileInvoice(ReconcileInvoiceRequest $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validated();

        /** @var Invoice $invoice */
        $invoice = Invoice::where('organization_id', $bankAccount->organization_id)
            ->findOrFail($validated['invoice_id']);

        try {
            $this->reconciliationService->reconcileWithInvoice($transaction, $invoice);
        } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.transaction_reconciled_invoice', ['number' => $invoice->number]));
    }

    /**
     * Reconcile a transaction with an expense.
     */
    public function reconcileExpense(ReconcileExpenseRequest $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validated();

        /** @var Expense $expense */
        $expense = Expense::where('organization_id', $bankAccount->organization_id)
            ->findOrFail($validated['expense_id']);

        try {
            $this->reconciliationService->reconcileWithExpense(
                $transaction,
                $expense,
                $validated['expense_account_code'],
            );
        } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.transaction_reconciled_expense'));
    }

    /**
     * Manually reconcile a transaction with a contra account.
     */
    public function reconcileManual(ReconcileManualRequest $request, BankTransaction $transaction): RedirectResponse
    {
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        $validated = $request->validated();

        try {
            $this->reconciliationService->reconcileWithContraAccount(
                $transaction,
                $validated['contra_account_code'],
            );
        } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.transaction_reconciled'));
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
        } catch (AlreadyReconciledException|UnlinkedBankAccountException|ReconciliationFailedException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.match_confirmed', ['number' => $match->invoice->number]));
    }

    /**
     * Auto-reconcile all unreconciled transactions (EE only).
     */
    public function autoReconcile(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        try {
            $result = $this->reconciliationService->autoReconcile($bankAccount);
        } catch (FeatureDisabledException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.auto_reconciliation_complete', ['matched' => $result['matched'], 'unmatched' => $result['unmatched']]));
    }

    /**
     * Mark a transaction as personal (mixed-use accounts only).
     */
    public function reconcilePersonal(BankTransaction $transaction): RedirectResponse
    {
        $transaction->loadMissing('bankAccount');
        /** @var BankAccount $bankAccount */
        $bankAccount = $transaction->bankAccount;
        $this->authorize('update', $bankAccount);

        if (! $bankAccount->is_mixed_use) {
            return redirect()->back()->with('error', __('app.mixed_use_required'));
        }

        try {
            $this->reconciliationService->reconcileAsPersonal($transaction);
        } catch (AlreadyReconciledException|UnlinkedBankAccountException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()
            ->with('success', __('app.transaction_marked_personal'));
    }

    /**
     * Bulk mark transactions as personal (mixed-use accounts only).
     */
    public function bulkReconcilePersonal(Request $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        if (! $bankAccount->is_mixed_use) {
            return redirect()->back()->with('error', __('app.mixed_use_required'));
        }

        $validated = $request->validate([
            'transaction_ids' => 'required|array|min:1',
            'transaction_ids.*' => 'integer|exists:bank_transactions,id',
        ]);

        $transactions = BankTransaction::with('bankAccount')
            ->where('bank_account_id', $bankAccount->id)
            ->whereIn('id', $validated['transaction_ids'])
            ->where('is_reconciled', false)
            ->get();

        $result = $this->reconciliationService->bulkReconcileAsPersonal($transactions);

        return redirect()->back()
            ->with('success', __('app.transactions_marked_personal', [
                'count' => $result['reconciled'],
            ]));
    }
}
