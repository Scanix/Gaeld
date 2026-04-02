<?php

namespace App\Domains\Reporting\Controllers;

use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Reporting\Requests\BalanceSheetRequest;
use App\Domains\Reporting\Requests\ProfitAndLossRequest;
use App\Domains\Reporting\Services\AgingReportService;
use App\Domains\Reporting\Services\ExportReportService;
use App\Domains\Reporting\Services\ReportingService;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

/**
 * Financial reports: P&L, balance sheet, cash flow, and aging analysis.
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
        ExportReportService $exporter,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $from = $validated['from'] ?? now()->startOfYear()->toDateString();
        $to = $validated['to'] ?? now()->toDateString();

        $report = $reportingService->profitAndLoss($orgId, $from, $to);
        $orgName = $currentOrg->get()->name;

        return $exporter->export(
            $format,
            csvBuilder: function () use ($exporter, $report, $from, $to) {
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

                return $exporter->csv()->export($headers, $rows, "profit-and-loss-{$from}-{$to}.csv");
            },
            pdfBuilder: fn () => $exporter->pdf()->download('exports.profit-and-loss', [
                'organizationName' => $orgName,
                'period' => ['from' => $from, 'to' => $to],
                'revenue' => $report['revenue'],
                'expenses' => $report['expenses'],
                'totalRevenue' => $report['total_revenue'],
                'totalExpenses' => $report['total_expenses'],
                'netProfit' => $report['net_profit'],
            ], "profit-and-loss-{$from}-{$to}.pdf"),
        );
    }

    public function exportBalanceSheet(
        BalanceSheetRequest $request,
        ReportingService $reportingService,
        CurrentOrganization $currentOrg,
        ExportReportService $exporter,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $asOfDate = $validated['as_of_date'] ?? now()->toDateString();

        $report = $reportingService->balanceSheet($orgId, $asOfDate);
        $orgName = $currentOrg->get()->name;

        return $exporter->export(
            $format,
            csvBuilder: function () use ($exporter, $report, $asOfDate) {
                $headers = ['Code', 'Account', 'Amount'];
                $rows = [];
                foreach (['assets' => 'Assets', 'liabilities' => 'Liabilities', 'equity' => 'Equity'] as $key => $label) {
                    $rows[] = ['', "--- {$label} ---", ''];
                    foreach ($report[$key]['accounts'] as $account) {
                        $rows[] = [$account['code'], $account['name'], $account['balance']];
                    }
                    $rows[] = ['', "Total {$label}", $report[$key]['total']];
                }

                return $exporter->csv()->export($headers, $rows, "balance-sheet-{$asOfDate}.csv");
            },
            pdfBuilder: fn () => $exporter->pdf()->download('exports.balance-sheet', [
                'organizationName' => $orgName,
                'asOfDate' => $asOfDate,
                'assets' => $report['assets'],
                'liabilities' => $report['liabilities'],
                'equity' => $report['equity'],
            ], "balance-sheet-{$asOfDate}.pdf"),
        );
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
        ExportReportService $exporter,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $report = $reportingService->cashFlow($currentOrg->id(), $from, $to);
        $orgName = $currentOrg->get()->name;

        return $exporter->export(
            $format,
            csvBuilder: function () use ($exporter, $report, $from, $to) {
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

                return $exporter->csv()->export($headers, $rows, "cash-flow-{$from}-{$to}.csv");
            },
            pdfBuilder: fn () => $exporter->pdf()->download('exports.cash-flow', [
                'organizationName' => $orgName,
                'period' => ['from' => $from, 'to' => $to],
                'report' => $report,
            ], "cash-flow-{$from}-{$to}.pdf"),
        );
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
        ExportReportService $exporter,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        $type = $request->input('type', 'receivables');
        abort_unless(in_array($type, ['receivables', 'payables'], true), 422);

        $asOfDate = $request->input('as_of_date');

        $report = $service->generate($currentOrg->id(), $type, $asOfDate);
        $orgName = $currentOrg->get()->name;

        return $exporter->export(
            $format,
            csvBuilder: function () use ($exporter, $report, $type) {
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

                return $exporter->csv()->export($headers, $rows, "aging-{$type}.csv");
            },
            pdfBuilder: fn () => $exporter->pdf()->download('exports.aging', [
                'organizationName' => $orgName,
                'report' => $report,
            ], "aging-{$type}.pdf"),
        );
    }
}
