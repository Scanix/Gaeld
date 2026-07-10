<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ConsolidationElimination;
use App\Domains\Accounting\Models\ConsolidationGroup;
use App\Domains\Accounting\Requests\StoreConsolidationEliminationRequest;
use App\Domains\Accounting\Requests\StoreConsolidationGroupRequest;
use App\Domains\Accounting\Services\ConsolidationReportService;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConsolidationController extends Controller
{
    public function index(): Response
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

    public function report(Request $request, ConsolidationGroup $group, CurrentOrganization $currentOrg, ConsolidationReportService $reportService): Response
    {
        $this->authorize('view', $group);

        $fiscalYear = (int) $request->input('fiscal_year', now()->year);

        $report = $reportService->build($group, $fiscalYear, $currentOrg->id());

        return Inertia::render('Accounting/Consolidation/Report', [
            'group' => $group,
            'fiscal_year' => $fiscalYear,
            'result' => $report['result'],
            'accountOptions' => $report['accountOptions'],
        ]);
    }

    public function storeElimination(StoreConsolidationEliminationRequest $request, ConsolidationGroup $group, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);
        $this->authorize('view', $group);

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

    public function destroyElimination(Request $request, ConsolidationElimination $consolidationElimination): RedirectResponse
    {
        abort_unless($request->user()?->hasPermissionTo(Permission::AccountingDelete), 403);

        $consolidationElimination->delete();

        return back()->with('success', __('app.deleted'));
    }
}
