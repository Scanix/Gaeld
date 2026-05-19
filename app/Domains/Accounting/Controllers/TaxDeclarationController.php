<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\TaxDeclaration;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Accounting\Requests\StoreTaxDeclarationRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TaxDeclarationController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $declarations = TaxDeclaration::query()
            ->where('organization_id', $currentOrg->id())
            ->orderByDesc('fiscal_year')
            ->orderBy('canton')
            ->get();

        return Inertia::render('Accounting/TaxDeclarations/Index', [
            'declarations' => $declarations,
        ]);
    }

    public function store(StoreTaxDeclarationRequest $request, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('create', Account::class);

        $validated = $request->validated();
        $validated['canton'] = strtoupper($validated['canton']);

        $declaration = TaxDeclaration::firstOrCreate(
            [
                'organization_id' => $currentOrg->id(),
                'fiscal_year' => $validated['fiscal_year'],
                'canton' => $validated['canton'],
            ],
            [
                'status' => 'draft',
                'data' => $this->buildSummaryData($currentOrg->id(), $validated['fiscal_year']),
            ],
        );

        if ($declaration->status === 'draft' && empty($declaration->data)) {
            $declaration->update([
                'data' => $this->buildSummaryData($currentOrg->id(), $validated['fiscal_year']),
            ]);
        }

        return redirect()->route('accounting.tax-declarations.show', $declaration)
            ->with('success', __('app.saved'));
    }

    public function show(TaxDeclaration $taxDeclaration, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('view', $taxDeclaration);

        if ($taxDeclaration->status === 'draft') {
            $taxDeclaration->update([
                'data' => $this->buildSummaryData($currentOrg->id(), $taxDeclaration->fiscal_year),
            ]);
            $taxDeclaration->refresh();
        }

        return Inertia::render('Accounting/TaxDeclarations/Show', [
            'declaration' => $taxDeclaration,
        ]);
    }

    public function finalize(Request $request, TaxDeclaration $taxDeclaration, CurrentOrganization $currentOrg): RedirectResponse
    {
        $this->authorize('update', $taxDeclaration);

        if ($taxDeclaration->status === 'draft') {
            $taxDeclaration->update([
                'status' => 'finalized',
                'finalized_at' => now(),
                'data' => $this->buildSummaryData($currentOrg->id(), $taxDeclaration->fiscal_year),
            ]);
        }

        return back()->with('success', __('app.saved'));
    }

    /**
     * @return array<string, float>
     */
    private function buildSummaryData(string $organizationId, int $fiscalYear): array
    {
        $lines = TransactionLine::query()
            ->select([
                'accounts.type as account_type',
                DB::raw('SUM(transaction_lines.debit) as total_debit'),
                DB::raw('SUM(transaction_lines.credit) as total_credit'),
            ])
            ->join('accounts', 'accounts.id', '=', 'transaction_lines.account_id')
            ->join('journal_entries', 'journal_entries.id', '=', 'transaction_lines.journal_entry_id')
            ->where('journal_entries.organization_id', $organizationId)
            ->where('journal_entries.is_posted', true)
            ->whereYear('journal_entries.date', $fiscalYear)
            ->groupBy('accounts.type')
            ->toBase()
            ->get();

        $totals = [
            'revenue' => 0.0,
            'expenses' => 0.0,
            'assets' => 0.0,
            'liabilities' => 0.0,
            'equity' => 0.0,
            'profit' => 0.0,
            'vat_payable_estimate' => 0.0,
            'net_result' => 0.0,
        ];

        foreach ($lines as $line) {
            /** @var object{account_type:string,total_credit:float|int|string,total_debit:float|int|string} $line */
            if ((string) $line->account_type === 'revenue') {
                $totals['revenue'] += (float) $line->total_credit - (float) $line->total_debit;
            }

            if ((string) $line->account_type === 'expense') {
                $totals['expenses'] += (float) $line->total_debit - (float) $line->total_credit;
            }

            if ((string) $line->account_type === 'asset') {
                $totals['assets'] += (float) $line->total_debit - (float) $line->total_credit;
            }

            if ((string) $line->account_type === 'liability') {
                $totals['liabilities'] += (float) $line->total_credit - (float) $line->total_debit;
            }

            if ((string) $line->account_type === 'equity') {
                $totals['equity'] += (float) $line->total_credit - (float) $line->total_debit;
            }
        }

        $totals['profit'] = $totals['revenue'] - $totals['expenses'];
        $totals['net_result'] = $totals['profit'];
        $totals['vat_payable_estimate'] = max(0, round($totals['revenue'] * 0.081, 2));

        return $totals;
    }
}
