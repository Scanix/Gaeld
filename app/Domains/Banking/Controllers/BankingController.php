<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\DTOs\CreateBankAccountData;
use App\Domains\Banking\DTOs\RecordBankTransactionData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Queries\BankAccountQuery;
use App\Domains\Banking\Requests\RecordTransactionRequest;
use App\Domains\Banking\Requests\StoreBankAccountRequest;
use App\Domains\Banking\Requests\UpdateBankAccountRequest;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Organizations\Services\CurrentOrganization;
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
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', BankAccount::class);

        return Inertia::render('Banking/Index', [
            'bankAccounts' => BankAccountQuery::list($request),
            'accounts' => Account::where('organization_id', $currentOrg->id())
                ->where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function show(BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        $transactions = $bankAccount->transactions()
            ->with('journalEntry')
            ->orderByDesc('date')
            ->paginate(config('accounting.pagination.default'));

        return Inertia::render('Banking/Show', [
            'bankAccount' => $bankAccount->load('ledgerAccount'),
            'transactions' => $transactions,
            'accounts' => Account::where('organization_id', $bankAccount->organization_id)
                ->where('is_active', true)
                ->select('id', 'code', 'name')
                ->orderBy('code')
                ->get(),
        ]);
    }

    public function store(StoreBankAccountRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $validated = $request->validated();
        $validated['organization_id'] = $currentOrg->id();

        $bankAccount = BankAccount::create(CreateBankAccountData::fromArray($validated)->toArray());

        if ($bankAccount->is_mixed_use) {
            $this->ensurePrivateWithdrawalsAccount($currentOrg->id());
        }

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.bank_account_created'));
    }

    public function update(UpdateBankAccountRequest $request, BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('update', $bankAccount);

        $bankAccount->update($request->validated());

        if ($bankAccount->is_mixed_use) {
            $this->ensurePrivateWithdrawalsAccount($bankAccount->organization_id);
        }

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.bank_account_updated'));
    }

    public function destroy(BankAccount $bankAccount): RedirectResponse
    {
        $this->authorize('delete', $bankAccount);

        $bankAccount->delete();

        return redirect()->route('banking.index')
            ->with('success', __('app.bank_account_deleted'));
    }

    public function recordTransaction(
        RecordTransactionRequest $request,
        BankAccount $bankAccount,
        BankingService $bankingService,
    ): RedirectResponse {
        $validated = $request->validated();

        $bankingService->recordTransaction(
            $bankAccount,
            RecordBankTransactionData::fromArray($validated),
        );

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', __('app.transaction_recorded'));
    }

    /**
     * Ensure the organization has a 2850 private withdrawals account.
     *
     * Created automatically when any bank account is flagged as mixed-use,
     * so the reconciliation flow can book personal transactions.
     */
    private function ensurePrivateWithdrawalsAccount(string $organizationId): void
    {
        $exists = Account::where('organization_id', $organizationId)
            ->where('code', AccountCode::PRIVATE_WITHDRAWALS)
            ->exists();

        if (! $exists) {
            Account::create([
                'organization_id' => $organizationId,
                'code' => AccountCode::PRIVATE_WITHDRAWALS,
                'name' => __('app.private_withdrawals_account'),
                'type' => AccountType::Equity->value,
                'is_active' => true,
            ]);
        }
    }
}
