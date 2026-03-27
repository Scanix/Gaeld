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

    public function vatReport(VatReportRequest $request, VatReportService $vatReportService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $validated = $request->validated();
        $report = $vatReportService->generate($currentOrg->id(), $validated['from_date'], $validated['to_date']);

        return Inertia::render('Reports/VatReport', [
            'report' => $report,
        ]);
    }

    public function exportVatReport(
        VatReportRequest $request,
        VatReportService $vatReportService,
        CurrentOrganization $currentOrg,
        PdfExportService $pdf,
        CsvExportService $csv,
        string $format,
    ): HttpResponse {
        $this->authorize('viewAny', Account::class);

        abort_unless(in_array($format, ['pdf', 'csv'], true), 404);

        $validated = $request->validated();
        $orgId = $currentOrg->id();
        $from = $validated['from_date'];
        $to = $validated['to_date'];

        $report = $vatReportService->generate($orgId, $from, $to);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Chiffre', 'Description', 'Amount'];
            $rows = [];
            foreach ($report['revenue_by_rate'] as $row) {
                $rows[] = ['200', "Revenue {$row['rate_name']} ({$row['rate']}%)", $row['base_amount']];
            }
            $rows[] = ['299', 'Total Revenue', $report['total_revenue']];
            foreach ($report['output_vat_by_rate'] as $row) {
                $rows[] = ['300', "Output VAT {$row['rate_name']} ({$row['rate']}%)", $row['amount']];
            }
            $rows[] = ['399', 'Total Output VAT', $report['total_output_vat']];
            $rows[] = ['400', 'Input VAT (deductible)', $report['input_vat']];
            $rows[] = ['500', 'Net VAT', $report['net_vat']];
            $rows[] = ['510', 'VAT Payable', $report['vat_payable']];

            return $csv->export($headers, $rows, "vat-report-{$from}-{$to}.csv");
        }

        return $pdf->download('exports.vat-report', [
            'organizationName' => $orgName,
            'report' => $report,
        ], "vat-report-{$from}-{$to}.pdf");
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
        ])->with('success', 'VAT settlement posted successfully.');
    }

    // ──────────────────────────────────────────────────────────────
    //  Cash Flow Report
    // ──────────────────────────────────────────────────────────────

    public function cashFlow(Request $request, ReportingService $reportingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $from = $request->input('from', now()->startOfYear()->toDateString());
        $to = $request->input('to', now()->toDateString());

        $report = $reportingService->cashFlow($currentOrg->id(), $from, $to);

        return Inertia::render('Reports/CashFlow', [
            'report' => $report,
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
        $orgId = $currentOrg->id();

        $report = $reportingService->cashFlow($orgId, $from, $to);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Section', 'Description', 'Amount'];
            $rows = [];
            $rows[] = ['Operating', 'Net Income', $report['net_income']];
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
            $rows[] = ['Summary', 'Beginning Cash', $report['beginning_cash']];
            $rows[] = ['Summary', 'Net Change in Cash', $report['net_change']];
            $rows[] = ['Summary', 'Ending Cash', $report['ending_cash']];

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

    public function aging(Request $request, AgingReportService $agingService, CurrentOrganization $currentOrg): Response
    {
        $this->authorize('viewAny', Account::class);

        $type = $request->input('type', 'receivables');
        abort_unless(in_array($type, ['receivables', 'payables'], true), 422);

        $asOfDate = $request->input('as_of_date');

        $report = $agingService->generate($currentOrg->id(), $type, $asOfDate);

        return Inertia::render('Reports/Aging', [
            'report' => $report,
        ]);
    }

    public function exportAging(
        Request $request,
        AgingReportService $agingService,
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
        $orgId = $currentOrg->id();

        $report = $agingService->generate($orgId, $type, $asOfDate);
        $orgName = $currentOrg->get()->name;

        if ($format === 'csv') {
            $headers = ['Bracket', 'Document #', 'Name', 'Date', 'Due Date', 'Amount', 'Days Overdue'];
            $rows = [];
            $bracketLabels = ['current' => 'Current', '1_30' => '1–30 days', '31_60' => '31–60 days', '61_90' => '61–90 days', '90_plus' => '90+ days'];
            foreach ($report['brackets'] as $key => $bracket) {
                foreach ($bracket['items'] as $item) {
                    $rows[] = [
                        $bracketLabels[$key] ?? $key,
                        $item['document_number'],
                        $item['name'],
                        $item['date'],
                        $item['due_date'],
                        $item['amount'],
                        $item['days_overdue'],
                    ];
                }
                $rows[] = [$bracketLabels[$key] ?? $key, '', 'Subtotal', '', '', $bracket['total'], ''];
            }
            $rows[] = ['', '', 'Grand Total', '', '', $report['grand_total'], ''];

            return $csv->export($headers, $rows, "aging-{$type}-{$report['as_of_date']}.csv");
        }

        return $pdf->download('exports.aging', [
            'organizationName' => $orgName,
            'report' => $report,
        ], "aging-{$type}-{$report['as_of_date']}.pdf");
    }
}
