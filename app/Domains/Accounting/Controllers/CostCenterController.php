<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\CostCenter;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Requests\StoreCostCenterRequest;
use App\Domains\Accounting\Requests\UpdateCostCenterRequest;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class CostCenterController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $centers = CostCenter::query()
            ->with(['children' => fn ($query) => $query
                ->where('organization_id', $currentOrg->id())
                ->orderBy('code')])
            ->where('organization_id', $currentOrg->id())
            ->whereNull('parent_id')
            ->orderBy('code')
            ->get();

        return Inertia::render('Accounting/CostCenters', [
            'costCenters' => $centers,
        ]);
    }

    public function store(StoreCostCenterRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();
        $validated['code'] = strtoupper($validated['code']);

        CostCenter::create([
            'organization_id' => $currentOrg->id(),
            'code' => $validated['code'],
            'name' => $validated['name'],
            'parent_id' => $validated['parent_id'] ?: null,
            'is_active' => true,
        ]);

        return back()->with('success', __('app.saved'));
    }

    public function update(UpdateCostCenterRequest $request, CostCenter $costCenter, CurrentOrganization $currentOrg): RedirectResponse
    {
        abort_unless($request->user()?->hasPermissionTo(Permission::AccountingEdit), 403);

        if ($costCenter->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        $validated = $request->validated();

        $updateData = [
            'code' => strtoupper($validated['code']),
            'name' => $validated['name'],
            'is_active' => (bool) $validated['is_active'],
        ];

        if (array_key_exists('parent_id', $validated)) {
            $updateData['parent_id'] = $validated['parent_id'];
        }

        $costCenter->update($updateData);

        return back()->with('success', __('app.saved'));
    }

    public function destroy(Request $request, CostCenter $costCenter, CurrentOrganization $currentOrg): RedirectResponse
    {
        abort_unless($request->user()?->hasPermissionTo(Permission::AccountingDelete), 403);

        if ($costCenter->organization_id !== $currentOrg->id()) {
            abort(404);
        }

        if ($costCenter->children()->exists()) {
            return back()->withErrors(['cost_center' => __('app.cannot_delete_with_children')]);
        }

        $used = TransactionLine::query()
            ->where('cost_center_id', (string) $costCenter->id)
            ->exists();

        if ($used) {
            return back()->withErrors(['cost_center' => __('app.cannot_delete_used_cost_center')]);
        }

        $costCenter->delete();

        return back()->with('success', __('app.deleted'));
    }

    public function analyticalReport(Request $request, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());
        $costCenterId = $request->input('cost_center_id');

        $baseQuery = TransactionLine::query()
            ->join('accounts', 'accounts.id', '=', 'transaction_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'transaction_lines.journal_entry_id')
            ->where('journal_entries.organization_id', $currentOrg->id())
            ->where('journal_entries.is_posted', true)
            ->whereBetween('journal_entries.date', [$from, $to]);

        if ($costCenterId) {
            $centerExists = CostCenter::query()
                ->where('organization_id', $currentOrg->id())
                ->whereKey((int) $costCenterId)
                ->exists();
            abort_unless($centerExists, 404);

            $baseQuery->where('transaction_lines.cost_center_id', (string) $costCenterId);
        }

        $rows = (clone $baseQuery)
            ->select([
                'accounts.code',
                'accounts.name',
                'accounts.type',
                DB::raw('SUM(transaction_lines.debit) as debit_total'),
                DB::raw('SUM(transaction_lines.credit) as credit_total'),
            ])
            ->groupBy('accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->toBase()
            ->get();

        $revenue = [];
        $expenses = [];
        $totalRevenue = 0.0;
        $totalExpenses = 0.0;

        foreach ($rows as $row) {
            /** @var object{code:string,name:string,type:string,debit_total:float|int|string,credit_total:float|int|string} $row */
            if ((string) $row->type === 'revenue') {
                $balance = (float) $row->credit_total - (float) $row->debit_total;
                $revenue[] = ['code' => $row->code, 'name' => $row->name, 'balance' => $balance];
                $totalRevenue += $balance;
            }

            if ((string) $row->type === 'expense') {
                $balance = (float) $row->debit_total - (float) $row->credit_total;
                $expenses[] = ['code' => $row->code, 'name' => $row->name, 'balance' => $balance];
                $totalExpenses += $balance;
            }
        }

        $costCenters = CostCenter::query()
            ->where('organization_id', $currentOrg->id())
            ->where('is_active', true)
            ->orderBy('code')
            ->get(['id', 'code', 'name']);

        return Inertia::render('Reports/AnalyticalReport', [
            'report' => [
                'revenue' => $revenue,
                'expenses' => $expenses,
                'total_revenue' => $totalRevenue,
                'total_expenses' => $totalExpenses,
                'net_profit' => $totalRevenue - $totalExpenses,
            ],
            'costCenters' => $costCenters,
            'filters' => [
                'from' => $from,
                'to' => $to,
                'cost_center_id' => $costCenterId,
            ],
        ]);
    }
}
