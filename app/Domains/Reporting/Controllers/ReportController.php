<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Actions\PostVatSettlementAction;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Requests\BalanceSheetRequest;
use App\Domains\Reporting\Requests\ProfitAndLossRequest;
use App\Domains\Reporting\Requests\VatReportRequest;
use App\Domains\Reporting\Services\AgingReportService;
use App\Domains\Reporting\Services\ReportingService;
use App\Http\Controllers\Controller;
use App\Support\CsvExportService;
use App\Support\PdfExportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Financial reports: balance sheet, income statement, trial balance,
 * VAT report, and aging analysis.
 */
class ReportController extends Controller
{
    public function profitAndLoss(ProfitAndLossRequest $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();

        $orgId = $currentOrg->id();
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $reportingService->profitAndLoss(
            $orgId,
            $from,
            $to,
            $validated['compare_from'] ?? null,
            $validated['compare_to'] ?? null,
        );

        return Inertia::render('Reports/ProfitAndLoss', [
            'report' => $report,
        ]);
    }

    public function balanceSheet(BalanceSheetRequest $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();

        $orgId = $currentOrg->id();
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();

        $report = $reportingService->balanceSheet($orgId, $asOfDate);

        return Inertia::render('Reports/BalanceSheet', [
            'report' => $report,
        ]);
    }

    public function exportProfitAndLoss(
        ProfitAndLossRequest $request,
        ReportingService $reportingService,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $reportingService->profitAndLoss($orgId, $from, $to);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Code', 'Account', 'Amount'];
            $rows = [];
            $rows[] = ['', '--- Revenue ---', ''];
            foreach ($report['revenue'] as $account) {
                $rows[] = [$account['code'], $account['name'], $account['balance']];
            }
            $rows[] = ['', 'Total Revenue', $report['total_revenue']];
            $rows[] = ['', '--- Expenses ---', ''];
            foreach ($report['expenses'] as $account) {
                $rows[] = [$account['code'], $account['name'], $account['balance']];
            }
            $rows[] = ['', 'Total Expenses', $report['total_expenses']];
            $rows[] = ['', 'Net Profit', $report['net_profit']];

            return $csv->export($headers, $rows, "profit-and-loss-{$from}-{$to}.csv");
        }

        return $pdf->download('exports.profit-and-loss', [
            'organizationName' => $orgName,
            'period' => ['from' => $from, 'to' => $to],
            'revenue' => $report['revenue'],
            'expenses' => $report['expenses'],
            'totalRevenue' => $report['total_revenue'],
            'totalExpenses' => $report['total_expenses'],
            'netProfit' => $report['net_profit'],
        ], "profit-and-loss-{$from}-{$to}.pdf");
    }

    public function exportBalanceSheet(
        BalanceSheetRequest $request,
        ReportingService $reportingService,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();

        $report = $reportingService->balanceSheet($orgId, $asOfDate);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Code', 'Account', 'Amount'];
            $rows = [];
            foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label) {
                $rows[] = ['', "--- {$label} ---", ''];
                foreach ($report[$key]['accounts'] as $account) {
                    $rows[] = [$account['code'], $account['name'], $account['balance']];
                }
                $rows[] = ['', "Total {$label}", $report[$key]['total']];
            }

            return $csv->export($headers, $rows, "balance-sheet-{$asOfDate}.csv");
        }

        return $pdf->download('exports.balance-sheet', [
            'organizationName' => $orgName,
            'asOfDate' => $asOfDate,
            'assets' => $report['assets'],
            'liabilities' => $report['liabilities'],
            'equity' => $report['equity'],
        ], "balance-sheet-{$asOfDate}.pdf");
    }

    // ──────────────────────────────────────────────────────────────
    //  VAT Report
    // ──────────────────────────────────────────────────────────────

    public function vatReport(Request $request, VatReportService $service, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from_date', $request->input('from', now()->startOfQuarter()->toDateString()));
        $to = $request->input('to_date', $request->input('to', now()->endOfQuarter()->toDateString()));

        $report = $service->generate($currentOrg->id(), $from, $to);

        return Inertia::render('Reports/VatReport', [
            'report' => $report,
        ]);
    }

    public function exportVatReport(
        VatReportRequest $request,
        VatReportService $service,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $validated = $request->validated();
        $report = $service->generate($currentOrg->id(), $validated['from_date'], $validated['to_date']);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Chiffre', 'Description', 'Base amount', 'VAT amount'];
            $rows = [];
            foreach ($report['revenue_by_rate'] as $line) {
                $rows[] = ['200', $line['rate'].'%', $line['base_amount'], $line['vat_amount']];
            }
            $rows[] = ['299', 'Total revenue', $report['total_revenue'], ''];
            foreach ($report['output_vat_by_rate'] as $line) {
                $rows[] = ['300', $line['rate'].'%', $line['base_amount'], $line['vat_amount']];
            }
            $rows[] = ['399', 'Total output VAT', '', $report['total_output_vat']];
            $rows[] = ['400', 'Input VAT', '', $report['input_vat']];
            $rows[] = ['500', 'Net VAT', '', $report['net_vat']];
            $rows[] = ['510', 'VAT payable', '', $report['vat_payable']];

            $from = $validated['from_date'];
            $to = $validated['to_date'];

            return $csv->export($headers, $rows, "vat-report-{$from}-{$to}.csv");
        }

        return $pdf->download('exports.vat-report', [
            'organizationName' => $orgName,
            'report' => $report,
        ], "vat-report-{$validated['from_date']}-{$validated['to_date']}.pdf");
    }

    public function postVatSettlement(
        VatReportRequest $request,
        PostVatSettlementAction $action,
        CurrentOrganization $currentOrg,
    ): RedirectResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $action->execute($currentOrg->id(), $validated['from_date'], $validated['to_date']);

        return redirect()->route('reports.vat', [
            'from_date' => $validated['from_date'],
            'to_date' => $validated['to_date'],
        ])->with('success', __('app.vat_settlement_posted'));
    }

    // ──────────────────────────────────────────────────────────────
    //  Cash Flow
    // ──────────────────────────────────────────────────────────────

    public function cashFlow(Request $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $report = $reportingService->cashFlow($currentOrg->id(), $from, $to);

        // Transform to the shape the Vue component expects
        $operating = $report['operating']['adjustments'] ?? [];
        if (isset($report['net_income']) && bccomp($report['net_income'], '0', 2) !== 0) {
            array_unshift($operating, ['label' => 'Net Income', 'amount' => $report['net_income']]);
        }

        return Inertia::render('Reports/CashFlow', [
            'report' => [
                'period' => $report['period'],
                'operating' => $operating,
                'investing' => $report['investing']['items'] ?? [],
                'financing' => $report['financing']['items'] ?? [],
                'net_change' => $report['net_change'] ?? '0.00',
                'beginning_balance' => $report['beginning_cash'] ?? '0.00',
                'ending_balance' => $report['ending_cash'] ?? '0.00',
            ],
        ]);
    }

    public function exportCashFlow(
        Request $request,
        ReportingService $reportingService,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $report = $reportingService->cashFlow($currentOrg->id(), $from, $to);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Section', 'Item', 'Amount'];
            $rows = [];
            $rows[] = ['', 'Net Income', $report['net_income']];
            $rows[] = ['Operating', '--- Adjustments ---', ''];
            foreach ($report['operating']['adjustments'] as $adj) {
                $rows[] = ['Operating', $adj['label'], $adj['amount']];
            }
            $rows[] = ['Operating', 'Total Operating', $report['operating']['total']];
            foreach ($report['investing']['items'] as $item) {
                $rows[] = ['Investing', $item['label'], $item['amount']];
            }
            $rows[] = ['Investing', 'Total Investing', $report['investing']['total']];
            foreach ($report['financing']['items'] as $item) {
                $rows[] = ['Financing', $item['label'], $item['amount']];
            }
            $rows[] = ['Financing', 'Total Financing', $report['financing']['total']];
            $rows[] = ['', 'Net Change', $report['net_change']];
            $rows[] = ['', 'Beginning Cash', $report['beginning_cash']];
            $rows[] = ['', 'Ending Cash', $report['ending_cash']];

            return $csv->export($headers, $rows, "cash-flow-{$from}-{$to}.csv");
        }

        return $pdf->download('exports.cash-flow', [
            'organizationName' => $orgName,
            'period' => ['from' => $from, 'to' => $to],
            'report' => $report,
        ], "cash-flow-{$from}-{$to}.pdf");
    }

    // ──────────────────────────────────────────────────────────────
    //  Aging Report
    // ──────────────────────────────────────────────────────────────

    public function aging(Request $request, AgingReportService $service, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $type = $request->input('type', 'receivables');
        abort_unless(in_array($type, ['receivables', 'payables'], true), 422);

        $asOfDate = $request->input('as_of_date');

        $report = $service->generate($currentOrg->id(), $type, $asOfDate);

        return Inertia::render('Reports/Aging', [
            'report' => $report,
        ]);
    }

    public function exportAging(
        Request $request,
        AgingReportService $service,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $type = $request->input('type', 'receivables');
        abort_unless(in_array($type, ['receivables', 'payables'], true), 422);

        $asOfDate = $request->input('as_of_date');

        $report = $service->generate($currentOrg->id(), $type, $asOfDate);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Name', 'Document #', 'Date', 'Due Date', 'Current', '1-30', '31-60', '61-90', '90+', 'Total'];
            $rows = [];
            foreach ($report['brackets'] as $bracket) {
                foreach ($bracket['items'] as $item) {
                    $rows[] = [
                        $item['name'],
                        $item['document_number'],
                        $item['date'],
                        $item['due_date'],
                        $item['bracket'] === 'current' ? $item['amount'] : '',
                        $item['bracket'] === '1_30' ? $item['amount'] : '',
                        $item['bracket'] === '31_60' ? $item['amount'] : '',
                        $item['bracket'] === '61_90' ? $item['amount'] : '',
                        $item['bracket'] === '90_plus' ? $item['amount'] : '',
                        $item['amount'],
                    ];
                }
            }
            $rows[] = ['', '', '', 'TOTAL',
                $report['brackets']['current']['total'] ?? '0.00',
                $report['brackets']['1_30']['total'] ?? '0.00',
                $report['brackets']['31_60']['total'] ?? '0.00',
                $report['brackets']['61_90']['total'] ?? '0.00',
                $report['brackets']['90_plus']['total'] ?? '0.00',
                $report['grand_total'],
            ];

            return $csv->export($headers, $rows, "aging-{$type}.csv");
        }

        return $pdf->download('exports.aging', [
            'organizationName' => $orgName,
            'report' => $report,
        ], "aging-{$type}.pdf");
    }
}
