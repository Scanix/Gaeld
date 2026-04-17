<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ConsolidationElimination;
use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Requests\StoreConsolidationEliminationRequest;
use App\Domains\Accounting\Requests\StoreConsolidationGroupRequest;
use App\Domains\Organizations\Enums\Permission;
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
            ->where('organization_id', $currentOrg->id())
            ->withCount('eliminations')
            ->orderBy('name')
            ->get();

        return Inertia::render('Accounting/Consolidation/Index', [
            'groups' => $groups,
        ]);
    }

    public function storeGroup(StoreConsolidationGroupRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();

        $members = array_values(array_unique($validated['member_organization_ids']));
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
        $this->authorize('viewAny', Account::class);

        if ($group->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        $fiscalYear = (int) $request->input('fiscal_year', now()->year);
        $memberIds = array_values(array_unique([
            $group->organization_id,
            ...($group->member_organization_ids ?? []),
        ]));

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
                DB::raw('SUM(transaction_lines.debit) as debit_total'),
                DB::raw('SUM(transaction_lines.credit) as credit_total'),
            ])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->get();

        $balances = [];
        foreach ($rows as $row) {
            $balances[(int) $row->account_id] = [
                'code' => $row->code,
                'name' => $row->name,
                'type' => $row->type,
                'balance' => (float) $row->debit_total - (float) $row->credit_total,
            ];
        }

        $eliminations = ConsolidationElimination::query()
            ->where('consolidation_group_id', $group->id)
            ->where('fiscal_year', $fiscalYear)
            ->orderByDesc('id')
            ->get();

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
            $type = (string) ($balance['type'] ?? '');
            $amount = abs((float) $balance['balance']);

            if ($type === 'asset') {
                $totals['assets'] += $amount;
            }
            if ($type === 'liability') {
                $totals['liabilities'] += $amount;
            }
            if ($type === 'equity') {
                $totals['equity'] += $amount;
            }
            if ($type === 'revenue') {
                $totals['revenue'] += $amount;
            }
            if ($type === 'expense') {
                $totals['expenses'] += $amount;
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
                'base_currency' => $group->base_currency,
            ],
        ]);
    }

    public function storeElimination(StoreConsolidationEliminationRequest $request, ConsolidationGroup $group, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        if ($group->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        $validated = $request->validated();

        $memberIds = array_values(array_unique([
            $group->organization_id,
            ...($group->member_organization_ids ?? []),
        ]));

        foreach (['account_debit_id', 'account_credit_id'] as $key) {
            $exists = Account::query()
                ->whereIn('organization_id', $memberIds)
                ->whereKey((int) $validated[$key])
                ->exists();
            abort_unless($exists, 422);
        }

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
        abort_unless($request->user()?->hasPermissionTo(Permission::AccountingDelete), 403);

        $group = ConsolidationGroup::query()->find($consolidationElimination->consolidation_group_id);
        if (! $group || $group->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        $consolidationElimination->delete();

        return back()->with('success', __('app.deleted'));
    }
}
