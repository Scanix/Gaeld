<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ConsolidationElimination;
use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Models\ExchangeRate;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Requests\StoreConsolidationEliminationRequest;
use App\Domains\Accounting\Requests\StoreConsolidationGroupRequest;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidationController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $groups = ConsolidationGroup::query()
            ->withCount('eliminations')
            ->orderBy('name')
            ->get();

        $organizationOptions = Organization::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization) => [
                'value' => $organization->id,
                'label' => $organization->name,
            ])
            ->values();

        return Inertia::render('Accounting/Consolidation/Index', [
            'groups' => $groups,
            'organizationOptions' => $organizationOptions,
        ]);
    }

    public function storeGroup(StoreConsolidationGroupRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();

        $members = Organization::query()
            ->whereIn('id', array_values(array_unique($validated['member_organization_ids'])))
            ->pluck('id')
            ->all();

        if (! in_array($currentOrg->id(), $members, true)) {
            $members[] = $currentOrg->id();
        }

        ConsolidationGroup::create([
            'organization_id' => $currentOrg->id(),
            'name' => $validated['name'],
            'member_organization_ids' => $members,
            'base_currency' => strtoupper($validated['base_currency']),
        ]);

        return back()->with('success', __('app.saved'));
    }

    public function report(Request $request, ConsolidationGroup $group, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('view', $group);

        $fiscalYear = (int) $request->input('fiscal_year', now()->year);
        $baseCurrency = strtoupper((string) $group->base_currency);
        $asOfDate = sprintf('%d-12-31', $fiscalYear);
        /** @var array<int, string> $memberOrganizationIds */
        $memberOrganizationIds = (array) $group->member_organization_ids;
        $memberIds = array_values(array_unique([
            $group->organization_id,
            ...$memberOrganizationIds,
        ]));

        $organizationCurrencies = Organization::query()
            ->whereIn('id', $memberIds)
            ->pluck('currency', 'id');

        $rows = TransactionLine::query()
            ->join('accounts', 'accounts.id', '=', 'transaction_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'transaction_lines.journal_entry_id')
            ->whereIn('journal_entries.organization_id', $memberIds)
            ->where('journal_entries.is_posted', true)
            ->whereYear('journal_entries.date', $fiscalYear)
            ->select([
                'accounts.id as account_id',
                'accounts.code',
                'accounts.name',
                'accounts.type',
                'journal_entries.organization_id as organization_id',
                DB::raw('SUM(transaction_lines.debit) as debit_total'),
                DB::raw('SUM(transaction_lines.credit) as credit_total'),
            ])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type', 'journal_entries.organization_id')
            ->orderBy('accounts.code')
            ->toBase()
            ->get();

        $balances = [];
        $conversionCache = [];
        $missingExchangeRates = [];
        foreach ($rows as $row) {
            /** @var object{account_id:int,code:string,name:string,type:string,organization_id:string,debit_total:float|int|string,credit_total:float|int|string} $row */
            $organizationCurrency = strtoupper((string) ($organizationCurrencies[$row->organization_id] ?? $baseCurrency));
            $conversionRate = $this->resolveConversionRate(
                $organizationCurrency,
                $baseCurrency,
                $asOfDate,
                $conversionCache,
                $missingExchangeRates,
            );

            $balances[(int) $row->account_id] = [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'balance' => ((float) $row->debit_total - (float) $row->credit_total) * $conversionRate,
            ];
        }

        $eliminations = ConsolidationElimination::query()
            ->where('consolidation_group_id', $group->id)
            ->where('fiscal_year', $fiscalYear)
            ->orderByDesc('id')
            ->get();

        $organizationNames = Organization::query()
            ->whereIn('id', $memberIds)
            ->pluck('name', 'id');

        $accountOptions = Account::query()
            ->whereIn('organization_id', $memberIds)
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name', 'organization_id'])
            ->map(fn (Account $account) => [
                'value' => $account->id,
                'label' => trim(sprintf(
                    '%s - %s (%s)',
                    $account->code,
                    $account->name,
                    (string) ($organizationNames[$account->organization_id] ?? $account->organization_id),
                )),
            ])
            ->values();

        foreach ($eliminations as $elim) {
            $debitId = (int) $elim->account_debit_id;
            $creditId = (int) $elim->account_credit_id;
            $amount = (float) $elim->amount;

            if (isset($balances[$debitId])) {
                $balances[$debitId]['balance'] += $amount;
            }
            if (isset($balances[$creditId])) {
                $balances[$creditId]['balance'] -= $amount;
            }
        }

        $totals = [
            'assets' => 0.0,
            'liabilities' => 0.0,
            'equity' => 0.0,
            'revenue' => 0.0,
            'expenses' => 0.0,
        ];

        foreach ($balances as $balance) {
            $type = $balance['type'];
            $signedBalance = (float) $balance['balance'];

            if ($type === 'asset') {
                $totals['assets'] += max($signedBalance, 0.0);
            } elseif ($type === 'liability') {
                $totals['liabilities'] += max(-$signedBalance, 0.0);
            } elseif ($type === 'equity') {
                $totals['equity'] += max(-$signedBalance, 0.0);
            } elseif ($type === 'revenue') {
                $totals['revenue'] += max(-$signedBalance, 0.0);
            } elseif ($type === 'expense') {
                $totals['expenses'] += max($signedBalance, 0.0);
            }
        }

        return Inertia::render('Accounting/Consolidation/Report', [
            'group' => $group,
            'fiscal_year' => $fiscalYear,
            'result' => [
                'accounts' => array_values($balances),
                'eliminations' => $eliminations,
                'assets' => $totals['assets'],
                'liabilities' => $totals['liabilities'],
                'equity' => $totals['equity'],
                'revenue' => $totals['revenue'],
                'expenses' => $totals['expenses'],
                'profit' => $totals['revenue'] - $totals['expenses'],
                'eliminations_applied' => $eliminations->count(),
                'base_currency' => $baseCurrency,
                'missing_exchange_rates' => array_keys($missingExchangeRates),
            ],
            'accountOptions' => $accountOptions,
        ]);
    }

    /**
     * @param  array<string, float>  $cache
     * @param  array<string, true>  $missingRates
     */
    private function resolveConversionRate(
        string $fromCurrency,
        string $toCurrency,
        string $asOfDate,
        array &$cache,
        array &$missingRates,
    ): float {
        if ($fromCurrency === $toCurrency) {
            return 1.0;
        }

        $cacheKey = $fromCurrency.'->'.$toCurrency.'@'.$asOfDate;
        if (isset($cache[$cacheKey])) {
            return $cache[$cacheKey];
        }

        $directRate = ExchangeRate::query()
            ->where('currency_from', $fromCurrency)
            ->where('currency_to', $toCurrency)
            ->whereDate('date', '<=', $asOfDate)
            ->orderByDesc('date')
            ->value('rate');

        if ($directRate !== null && (float) $directRate > 0) {
            return $cache[$cacheKey] = (float) $directRate;
        }

        $inverseRate = ExchangeRate::query()
            ->where('currency_from', $toCurrency)
            ->where('currency_to', $fromCurrency)
            ->whereDate('date', '<=', $asOfDate)
            ->orderByDesc('date')
            ->value('rate');

        if ($inverseRate !== null && (float) $inverseRate > 0) {
            return $cache[$cacheKey] = 1 / (float) $inverseRate;
        }

        $missingRates[$fromCurrency.'->'.$toCurrency] = true;

        return $cache[$cacheKey] = 1.0;
    }

    public function storeElimination(StoreConsolidationEliminationRequest $request, ConsolidationGroup $group, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $group);

        $validated = $request->validated();

        ConsolidationElimination::create([
            'organization_id' => $currentOrg->id(),
            'consolidation_group_id' => $group->id,
            'account_debit_id' => (int) $validated['account_debit_id'],
            'account_credit_id' => (int) $validated['account_credit_id'],
            'amount' => $validated['amount'],
            'fiscal_year' => (int) $validated['fiscal_year'],
            'description' => $validated['description'] ?? null,
        ]);

        return back()->with('success', __('app.saved'));
    }

    public function destroyElimination(Request $request, ConsolidationElimination $consolidationElimination, CurrentOrganization $currentOrg): RedirectResponse
    {
        $group = ConsolidationGroup::query()->find($consolidationElimination->consolidation_group_id);
        abort_unless($group !== null, 404);
        $this->authorize('delete', $group);

        $consolidationElimination->delete();

        return back()->with('success', __('app.deleted'));
    }
}
