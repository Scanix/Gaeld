<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class YearEndClosingController extends Controller
{
    public function index(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $orgId   = $currentOrg->id();
        $year    = $request->integer('year', now()->subYear()->year);
        $from    = "{$year}-01-01";
        $to      = "{$year}-12-31";

        [$income, $expenses, $netResult] = $this->computeClosingAccounts($orgId, $from, $to);

        return Inertia::render('Accounting/YearEndClosing', [
            'year'        => $year,
            'fromDate'    => $from,
            'toDate'      => $to,
            'income'      => $income,
            'expenses'    => $expenses,
            'netResult'   => $netResult,
        ]);
    }

    public function store(Request $request, CurrentOrganization $currentOrg, LedgerService $ledger): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $orgId = $currentOrg->id();

        $validated = $request->validate([
            'year'                => 'required|integer|min:2000|max:2100',
            'closing_date'        => 'required|date',
            'reference'           => 'required|string|max:50',
            'result_account_code' => 'required|string|max:20',
        ]);

        $year  = (int) $validated['year'];
        $from  = "{$year}-01-01";
        $to    = "{$year}-12-31";

        [$income, $expenses] = $this->computeClosingAccounts($orgId, $from, $to);

        $allAccounts = array_merge($income, $expenses);

        if (empty($allAccounts)) {
            return redirect()->back()->with('error', 'No accounts to close for this period.');
        }

        // Find the result account
        $resultAccount = Account::where('organization_id', $orgId)
            ->where('code', $validated['result_account_code'])
            ->first();

        if (! $resultAccount) {
            return redirect()->back()->withErrors([
                'result_account_code' => "Account '{$validated['result_account_code']}' not found.",
            ]);
        }

        try {
            DB::transaction(function () use ($income, $expenses, $year, $validated, $resultAccount, $orgId, $ledger) {
                // Build closing journal lines
                // Revenue (credit-normal): debit the account, credit result
                // Expense (debit-normal):  credit the account, debit result
                $lines = [];
                $netDebitOnResult  = '0';  // debits to result account (for expenses)
                $netCreditOnResult = '0';  // credits to result account (for revenues)

                foreach ($income as $row) {
                    if (bccomp((string) $row['balance'], '0', 2) === 0) {
                        continue;
                    }
                    $lines[] = new JournalLineData(
                        accountId: (string) $row['account_id'],
                        debit:     (string) $row['balance'],
                        credit:    '0',
                        description: "Bouclement {$year} — clôture " . $row['code'],
                    );
                    $netCreditOnResult = bcadd($netCreditOnResult, (string) $row['balance'], 2);
                }

                foreach ($expenses as $row) {
                    if (bccomp((string) $row['balance'], '0', 2) === 0) {
                        continue;
                    }
                    $lines[] = new JournalLineData(
                        accountId: (string) $row['account_id'],
                        debit:     '0',
                        credit:    (string) $row['balance'],
                        description: "Bouclement {$year} — clôture " . $row['code'],
                    );
                    $netDebitOnResult = bcadd($netDebitOnResult, (string) $row['balance'], 2);
                }

                // Add result account line (net debit or credit)
                $netDebit  = $netDebitOnResult;
                $netCredit = $netCreditOnResult;

                $lines[] = new JournalLineData(
                    accountId: (string) $resultAccount->id,
                    debit:     $netDebit,
                    credit:    $netCredit,
                    description: "Bouclement {$year} — résultat de l'exercice",
                );

                $entry = new JournalEntryData(
                    date:        $validated['closing_date'],
                    reference:   $validated['reference'],
                    description: "Bouclement de compte {$year}",
                    lines:       $lines,
                );

                $ledger->postEntry($orgId, $entry);
            });
        } catch (\Throwable $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('accounting.closing')
            ->with('success', 'Bouclement de compte effectué.');
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * @return array{0: array[], 1: array[], 2: string}
     */
    private function computeClosingAccounts(string $orgId, string $from, string $to): array
    {
        $accounts = Account::where('organization_id', $orgId)
            ->where('is_active', true)
            ->whereIn('type', [AccountType::Revenue->value, AccountType::Expense->value])
            ->orderBy('code')
            ->get();

        $income   = [];
        $expenses = [];
        $net      = '0';

        foreach ($accounts as $account) {
            $query = TransactionLine::where('account_id', $account->id)
                ->whereHas('journalEntry', fn ($q) => $q
                    ->where('is_posted', true)
                    ->where('date', '>=', $from)
                    ->where('date', '<=', $to)
                );

            $debits  = (string) (clone $query)->sum('debit');
            $credits = (string) (clone $query)->sum('credit');

            // Revenue is credit-normal: balance = credits − debits
            // Expense is debit-normal:  balance = debits − credits
            $isDebitNormal = $account->type->isDebitNormal();
            $balance = $isDebitNormal
                ? bcsub($debits, $credits, 2)
                : bcsub($credits, $debits, 2);

            if (bccomp($balance, '0', 2) === 0) {
                continue;
            }

            $row = [
                'account_id' => $account->id,
                'code'       => $account->code,
                'name'       => $account->name,
                'balance'    => $balance,
            ];

            if ($account->type === AccountType::Revenue) {
                $income[] = $row;
                $net = bcadd($net, $balance, 2);
            } else {
                $expenses[] = $row;
                $net = bcsub($net, $balance, 2);
            }
        }

        return [$income, $expenses, $net];
    }
}
