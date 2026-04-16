<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Support\CsvExportService;
use App\Support\PdfExportService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Journal entry management: listing, creating, posting, and reversing entries.
 */
class AccountingController extends Controller
{
    public function chartOfAccounts(Request $request): Response
    {
        $this->authorize('viewAny', Account::class);

        $query = Account::withCount('transactionLines')
            ->orderBy('code');

        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('code', 'ilike', "%{$search}%")
                    ->orWhere('name', 'ilike', "%{$search}%");
            });
        }

        $accounts = $query
            ->paginate(50)
            ->withQueryString()
            ->through(fn (Account $a) => [
                ...$a->toArray(),
                'has_transactions' => $a->transaction_lines_count > 0,
            ]);

        $user = $request->user();

        return Inertia::render('Accounting/ChartOfAccounts', [
            'accounts' => $accounts,
            'query' => ['search' => $request->input('search', '')],
            'can' => [
                'create' => $user->can('create', Account::class),
                'edit' => $user->hasPermissionTo(Permission::AccountingEdit),
                'delete' => $user->hasPermissionTo(Permission::AccountingDelete),
            ],
            'accountTypes' => array_map(fn ($t) => ['value' => $t->value, 'label' => __("app.account_type_{$t->value}")], AccountType::cases()),
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

    public function trialBalance(Request $request, LedgerQueryService $ledgerService, CurrentOrganization $currentOrg): Response
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

    public function exportTrialBalance(
        Request $request,
        LedgerQueryService $ledgerService,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $orgId = $currentOrg->id();
        $asOfDate = $request->input('as_of_date', now()->toDateString());
        $balances = $ledgerService->trialBalance($orgId, $asOfDate);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Code', 'Account', 'Type', 'Debit', 'Credit'];
            $rows = array_map(fn ($row) => [
                $row['account_code'],
                $row['account_name'],
                $row['account_type'],
                $row['debit'],
                $row['credit'],
            ], $balances);

            return $csv->export($headers, $rows, "trial-balance-{$asOfDate}.csv");
        }

        return $pdf->download('exports.trial-balance', [
            'organizationName' => $orgName,
            'asOfDate' => $asOfDate,
            'balances' => $balances,
        ], "trial-balance-{$asOfDate}.pdf");
    }

    public function exportJournalEntries(
        Request $request,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', JournalEntry::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $orgId = $currentOrg->id();
        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $entries = JournalEntry::where('organization_id', $orgId)
            ->where('is_posted', true)
            ->whereBetween('date', [$from, $to])
            ->with('lines.account')
            ->orderBy('date')
            ->orderBy('created_at')
            ->get();

        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Date', 'Reference', 'Description', 'Account Code', 'Account Name', 'Debit', 'Credit'];
            $rows = [];
            foreach ($entries as $entry) {
                foreach ($entry->lines as $line) {
                    $rows[] = [
                        $entry->date->format('Y-m-d'),
                        $entry->reference,
                        $entry->description,
                        $line->account->code ?? '',
                        $line->account->name ?? '',
                        (string) $line->debit,
                        (string) $line->credit,
                    ];
                }
            }

            return $csv->export($headers, $rows, "journal-entries-{$from}-{$to}.csv");
        }

        return $pdf->download('exports.journal-entries', [
            'organizationName' => $orgName,
            'fromDate' => $from,
            'toDate' => $to,
            'entries' => $entries,
        ], "journal-entries-{$from}-{$to}.pdf");
    }
}
