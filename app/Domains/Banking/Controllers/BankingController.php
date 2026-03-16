<?php

namespace App\Domains\Banking\Controllers;

use App\Domains\Banking\Actions\CreateBankAccountAction;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\BankingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BankingController extends Controller
{
    public function index(Request $request): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(! $orgId, 403, 'No organization found.');
        $this->authorize('viewAny', BankAccount::class);

        $bankAccounts = BankAccount::where('organization_id', $orgId)
            ->with('ledgerAccount')
            ->orderBy('name')
            ->get();

        return Inertia::render('Banking/Index', [
            'bankAccounts' => $bankAccounts,
        ]);
    }

    public function show(BankAccount $bankAccount): Response
    {
        $this->authorize('view', $bankAccount);

        $transactions = $bankAccount->transactions()
            ->with('journalEntry')
            ->orderByDesc('date')
            ->paginate(20);

        return Inertia::render('Banking/Show', [
            'bankAccount' => $bankAccount->load('ledgerAccount'),
            'transactions' => $transactions,
        ]);
    }

    public function store(Request $request, CreateBankAccountAction $action): RedirectResponse
    {
        $this->authorize('create', BankAccount::class);

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'iban' => 'nullable|string|max:34',
            'bank_name' => 'nullable|string|max:255',
            'account_id' => 'nullable|exists:accounts,id',
            'currency' => 'string|size:3',
        ]);

        $validated['organization_id'] = $request->user()->resolveCurrentOrganization()->id;

        $bankAccount = $action->execute($validated);

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', 'Bank account created.');
    }

    public function recordTransaction(
        Request $request,
        BankAccount $bankAccount,
        BankingService $bankingService,
    ): RedirectResponse {
        $this->authorize('update', $bankAccount);

        $validated = $request->validate([
            'date' => 'required|date',
            'description' => 'nullable|string|max:500',
            'amount' => 'required|numeric|min:0.01',
            'type' => 'required|in:credit,debit',
            'reference' => 'nullable|string|max:100',
            'contra_account_code' => 'required|string|max:10',
        ]);

        $bankingService->recordTransaction(
            $bankAccount,
            $validated,
            $validated['contra_account_code'],
        );

        return redirect()->route('banking.show', $bankAccount)
            ->with('success', 'Transaction recorded.');
    }
}
