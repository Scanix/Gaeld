<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Queries\AccountQuery;
use App\Domains\Banking\Actions\CreateBankAccountAction;
use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\DTOs\RecordBankTransactionData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Queries\BankAccountQuery;
use App\Domains\Banking\Requests\RecordTransactionRequest;
use App\Domains\Banking\Requests\StoreBankAccountRequest;
use App\Domains\Banking\Requests\UpdateBankAccountRequest;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Bank account and transaction management, including CAMT file import.
 */
class BankingController extends Controller
{
    use HandlesFlashErrorResponses;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccountQuery::list($request);
        // Attach the GL-derived balance to each item for the listing view
        foreach ($bankAccounts->items() as $ba) {
            $ba->setAttribute('derived_balance', $ba->derivedBalance());
        }

        return Inertia::render('Banking/Index', [
            'bankAccounts' => $bankAccounts,
            'accounts' => AccountQuery::cashOrBankForSelect(),
        ]);
    }

    public function show(BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        $transactions = $bankAccount->transactions()
            ->with('journalEntry')
            ->orderByDesc('date')
            ->paginate(config('accounting.pagination.default'));

        $bankAccount->load('ledgerAccount');
        $bankAccount->setAttribute('derived_balance', $bankAccount->derivedBalance());

        // Ledger movements: every TransactionLine touching this bank's GL account.
        // Surfaced so the bank-detail page reflects activity even before any
        // CAMT statement has been imported (e.g. invoice payments recorded
        // against this account via "Record Payment").
        $ledgerMovements = collect();
        if ($bankAccount->account_id) {
            $ledgerMovements = TransactionLine::query()
                ->where('account_id', $bankAccount->account_id)
                ->with(['journalEntry:id,date,description,reference'])
                ->whereHas('journalEntry')
                ->get(['id', 'journal_entry_id', 'debit', 'credit', 'description'])
                ->sortByDesc(fn ($l) => $l->journalEntry?->date)
                ->take(50)
                ->values()
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'date' => $l->journalEntry?->date?->toDateString(),
                    'description' => $l->description ?: $l->journalEntry?->description,
                    'reference' => $l->journalEntry?->reference,
                    'debit' => (string) $l->debit,
                    'credit' => (string) $l->credit,
                ]);
        }

        return Inertia::render('Banking/Show', [
            'bankAccount' => $bankAccount,
            'transactions' => $transactions,
            'ledgerMovements' => $ledgerMovements,
            'accounts' => Account::query()
                ->where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(
        StoreBankAccountRequest $request,
        CurrentOrganization $currentOrg,
        CreateBankAccountAction $action,
    ): RedirectResponse {
        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $bankAccount = $action->execute(CreateBankAccountData::fromArray($validated));

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.bank_account_created'));
    }

    public function update(
        UpdateBankAccountRequest $request,
        BankAccount $bankAccount,
        CreateBankAccountAction $action,
    ): RedirectResponse {
        $this->authorize('update', $bankAccount);

        $bankAccount->update($request->validated());
        $action->configure($bankAccount);

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.bank_account_updated'));
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        $organizationId = $bankAccount->organization_id;
        $bankAccount->delete();
        BankAccountQuery::forgetSelectCache($organizationId);

        return redirect()->route('banking.index')
            ->with('success', __('app.bank_account_deleted'));
    }

    public function recordTransaction(
        RecordTransactionRequest $request,
        BankAccount $bankAccount,
        BankingService $bankingService,
    ): RedirectResponse {
        $validated = $request->validated();

        try {
            $bankingService->recordTransaction(
                $bankAccount,
                RecordBankTransactionData::fromArray($validated),
            );
        } catch (DuplicateReferenceException $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.transaction_recorded'));
    }
}
