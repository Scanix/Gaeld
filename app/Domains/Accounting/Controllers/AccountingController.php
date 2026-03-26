<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function chartOfAccounts(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $accounts = Account::withCount('transactionLines')
            ->orderBy('code')
            ->get()
            ->map(fn (Account $a) => [
                ...$a->toArray(),
                'has_transactions' => $a->transaction_lines_count > 0,
            ]);

        $user = $request->user();

        return Inertia::render('Accounting/ChartOfAccounts', [
            'accounts' => $accounts,
            'can' => [
                'create' => $user->can('create', Account::class),
                'edit' => $user->hasPermissionTo(\App\Domains\Organizations\Enums\Permission::AccountingEdit),
                'delete' => $user->hasPermissionTo(\App\Domains\Organizations\Enums\Permission::AccountingDelete),
            ],
            'accountTypes' => array_map(fn ($t) => ['value' => $t->value, 'label' => $t->value], AccountType::cases()),
        ]);
    }

    public function journalEntries(Request $request): Response
    {
        $this->authorize('viewAny', JournalEntry::class);

        $entries = JournalEntry::with('lines.account')
            ->orderByDesc('date')
            ->paginate(config('accounting.pagination.default'));

        return Inertia::render('Accounting/JournalEntries', [
            'entries' => $entries,
        ]);
    }

    public function trialBalance(Request $request, LedgerService $ledgerService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $orgId = $currentOrg->id();
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        $balances = $ledgerService->trialBalance($orgId, $asOfDate);

        return Inertia::render('Accounting/TrialBalance', [
            'balances' => $balances,
            'asOfDate' => $asOfDate,
        ]);
    }
}
