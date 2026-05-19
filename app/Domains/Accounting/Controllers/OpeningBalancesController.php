<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Actions\RecordOpeningBalancesAction;
use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Requests\StoreHistoricalSummaryRequest;
use App\Domains\Accounting\Requests\StoreOpeningBalancesRequest;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Concerns\HandlesFlashErrorResponses;
use App\Http\Controllers\Controller;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Wizard for seeding opening balances when an organization starts using
 * Gäld without a prior closed fiscal year in the system.
 */
class OpeningBalancesController extends Controller
{
    use HandlesFlashErrorResponses;

    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', JournalEntry::class);

        $org = $currentOrg->get();

        $balanceSheetTypes = [
            AccountType::Asset->value,
            AccountType::Liability->value,
            AccountType::Equity->value,
        ];

        $accounts = Account::query()
            ->where('is_active', true)
            ->whereIn('type', $balanceSheetTypes)
            ->where('code', '!=', AccountCode::OPENING_BALANCE)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'type'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->display_name,
                'type' => $a->type->value,
            ]);

        $existingOpening = JournalEntry::query()
            ->where('reference', 'like', 'OPENING-%')
            ->where('is_posted', true)
            ->orderByDesc('date')
            ->first(['id', 'date', 'reference']);

        $equityAccounts = Account::query()
            ->where('is_active', true)
            ->where('type', AccountType::Equity->value)
            ->where('code', '!=', AccountCode::OPENING_BALANCE)
            ->orderBy('code')
            ->get(['id', 'code', 'name'])
            ->map(fn (Account $a) => [
                'id' => $a->id,
                'code' => $a->code,
                'name' => $a->display_name,
            ]);

        $existingHistorical = JournalEntry::query()
            ->where('type', 'historical_summary')
            ->where('is_posted', true)
            ->orderByDesc('date')
            ->first(['id', 'date', 'reference']);

        return Inertia::render('Accounting/OpeningBalances', [
            'accounts' => $accounts,
            'defaultDate' => sprintf('%d-01-01', now()->year),
            'existingOpening' => $existingOpening,
            'isStartingFresh' => $org->setup_mode === 'fresh',
            'equityAccounts' => $equityAccounts,
            'existingHistorical' => $existingHistorical,
        ]);
    }

    public function store(
        StoreOpeningBalancesRequest $request,
        CurrentOrganization $currentOrg,
        RecordOpeningBalancesAction $action,
    ): RedirectResponse {
        $this->authorize('create', JournalEntry::class);

        $validated = $request->validated();

        try {
            $entry = $action->execute(
                orgId: $currentOrg->id(),
                date: $validated['date'],
                balances: $validated['balances'],
                reference: $validated['reference'] ?? null,
                description: $validated['description'] ?? null,
            );
        } catch (\Throwable $e) {
            return $this->backWithError($e);
        }

        if ($entry === null) {
            return $this->backWithError(__('app.opening_balances_all_zero'));
        }

        return redirect()->route('accounting.journal')
            ->with('success', __('app.opening_balances_recorded'));
    }

    public function storeHistorical(
        StoreHistoricalSummaryRequest $request,
        CurrentOrganization $currentOrg,
        LedgerService $ledger,
        LedgerQueryService $ledgerQuery,
    ): RedirectResponse {
        $this->authorize('create', JournalEntry::class);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $amount = (string) $validated['amount'];
        $year = Carbon::parse($validated['date'])->year;

        try {
            $openingAccount = $ledgerQuery->resolveAccount($orgId, AccountCode::OPENING_BALANCE);
            $absAmount = Money::isNegative($amount) ? Money::negate($amount) : $amount;
            $isProfit = Money::isPositive($amount);

            $lines = [
                new JournalLineData(
                    accountId: (string) $validated['account_id'],
                    debit: $isProfit ? '0' : $absAmount,
                    credit: $isProfit ? $absAmount : '0',
                    description: __('app.historical_summary_badge'),
                ),
                new JournalLineData(
                    accountId: (string) $openingAccount->id,
                    debit: $isProfit ? $absAmount : '0',
                    credit: $isProfit ? '0' : $absAmount,
                    description: __('app.historical_summary_badge'),
                ),
            ];

            $entry = $ledger->postEntry($orgId, new JournalEntryData(
                date: $validated['date'],
                reference: $validated['reference'] ?? "HIST-{$year}",
                description: $validated['description'] ?? __('app.historical_summary_card_title'),
                lines: $lines,
            ));

            $entry->update(['type' => 'historical_summary']);
        } catch (\Throwable $e) {
            return $this->backWithError($e);
        }

        return redirect()->route('accounting.opening-balances.index')
            ->with('success', __('app.historical_summary_saved'));
    }
}
