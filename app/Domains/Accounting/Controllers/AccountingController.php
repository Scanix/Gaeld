<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    public function chartOfAccounts(Request $request): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');

        $accounts = Account::where('organization_id', $orgId)
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/ChartOfAccounts', [
            'accounts' => $accounts,
        ]);
    }

    public function journalEntries(Request $request): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');

        $entries = JournalEntry::where('organization_id', $orgId)
            ->with('lines.account')
            ->orderByDesc('date')
            ->paginate(20);

        return Inertia::render('Accounting/JournalEntries', [
            'entries' => $entries,
        ]);
    }

    public function trialBalance(Request $request, LedgerService $ledgerService): Response
    {
        $orgId = $request->user()->resolveCurrentOrganization()?->id;
        abort_if(!$orgId, 403, 'No organization found.');
        $asOfDate = $request->input('as_of_date', now()->toDateString());

        $balances = $ledgerService->trialBalance($orgId, $asOfDate);

        return Inertia::render('Accounting/TrialBalance', [
            'balances' => $balances,
            'asOfDate' => $asOfDate,
        ]);
    }
}
