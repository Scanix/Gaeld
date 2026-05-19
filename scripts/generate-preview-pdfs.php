<?php

/**
 * Generates one sample PDF for each of the 8 export templates.
 * Run via: vendor/bin/sail artisan tinker --execute 'require base_path("scripts/generate-preview-pdfs.php");'
 */

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Invoicing\Actions\GenerateQrInvoicePdfAction;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Payroll\Models\SalarySlip;
use App\Domains\Reporting\Services\AgingReportService;
use App\Domains\Reporting\Services\ReportingService;
use Barryvdh\DomPDF\Facade\Pdf;

$outDir = public_path('pdf-preview');
if (! is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

$org = Organization::where('name', 'Helvetia Studio SA')->firstOrFail();
$orgId = $org->id;
$from = '2025-01-01';
$to = '2025-12-31';
$asOf = '2025-12-31';

echo "Org: {$org->name} ({$orgId})\n";

// 1. Profit & Loss
$reportingService = app(ReportingService::class);
$report = $reportingService->profitAndLoss($orgId, $from, $to);
Pdf::loadView('exports.profit-and-loss', [
    'organization' => $org,
    'period' => ['from' => $from, 'to' => $to],
    'revenue' => $report['revenue'],
    'expenses' => $report['expenses'],
    'totalRevenue' => $report['total_revenue'],
    'totalExpenses' => $report['total_expenses'],
    'netProfit' => $report['net_profit'],
])->setPaper('A4', 'portrait')->save("{$outDir}/1-profit-and-loss.pdf");
echo "1. Profit & Loss ✓\n";

// 2. Balance Sheet
$report = $reportingService->balanceSheet($orgId, $asOf);
Pdf::loadView('exports.balance-sheet', [
    'organization' => $org,
    'asOfDate' => $asOf,
    'assets' => $report['assets'],
    'liabilities' => $report['liabilities'],
    'equity' => $report['equity'],
])->setPaper('A4', 'portrait')->save("{$outDir}/2-balance-sheet.pdf");
echo "2. Balance Sheet ✓\n";

// 3. Trial Balance
$ledgerService = app(LedgerQueryService::class);
$balances = $ledgerService->trialBalance($orgId, $asOf);
Pdf::loadView('exports.trial-balance', [
    'organization' => $org,
    'asOfDate' => $asOf,
    'balances' => $balances,
])->setPaper('A4', 'portrait')->save("{$outDir}/3-trial-balance.pdf");
echo "3. Trial Balance ✓\n";

// 4. Journal Entries
$entries = JournalEntry::query()
    ->where('organization_id', $orgId)
    ->where('is_posted', true)
    ->whereBetween('date', [$from, $to])
    ->with('lines.account')
    ->orderBy('date')
    ->orderBy('id')
    ->get();
Pdf::loadView('exports.journal-entries', [
    'organization' => $org,
    'fromDate' => $from,
    'toDate' => $to,
    'entries' => $entries,
])->setPaper('A4', 'portrait')->save("{$outDir}/4-journal-entries.pdf");
echo "4. Journal Entries ({$entries->count()} entries) ✓\n";

// 5. VAT Report
$vatService = app(VatReportService::class);
$vatReport = $vatService->generate($orgId, $from, $to);
Pdf::loadView('exports.vat-report', [
    'organization' => $org,
    'report' => $vatReport,
])->setPaper('A4', 'portrait')->save("{$outDir}/5-vat-report.pdf");
echo "5. VAT Report ✓\n";

// 6. Cash Flow
$cashFlow = $reportingService->cashFlow($orgId, $from, $to);
Pdf::loadView('exports.cash-flow', [
    'organization' => $org,
    'period' => ['from' => $from, 'to' => $to],
    'report' => $cashFlow,
])->setPaper('A4', 'portrait')->save("{$outDir}/6-cash-flow.pdf");
echo "6. Cash Flow ✓\n";

// 7. Aging (receivables)
$agingService = app(AgingReportService::class);
$agingReport = $agingService->generate($orgId, 'receivables', $asOf);
Pdf::loadView('exports.aging', [
    'organization' => $org,
    'report' => $agingReport,
    'type' => 'receivables',
    'asOfDate' => $asOf,
])->setPaper('A4', 'portrait')->save("{$outDir}/7-aging-receivables.pdf");
echo "7. Aging (receivables) ✓\n";

// 8. Salary Slip
$slip = SalarySlip::where('organization_id', $orgId)
    ->with('employee')
    ->latest()
    ->firstOrFail();
Pdf::loadView('exports.salary-slip', [
    'slip' => $slip,
    'organization' => $org,
])->setPaper('A4', 'portrait')->save("{$outDir}/8-salary-slip.pdf");
echo "8. Salary Slip ({$slip->employee->fullName()}) ✓\n";

// 9. Invoice (Swiss QR-bill PDF via TCPDF)
$invoice = Invoice::where('organization_id', $orgId)->with(['customer', 'lines.vatRate'])->first();
if ($invoice) {
    $invoice->qr_iban = 'CH4431999123000889012';
    $qrAction = app(GenerateQrInvoicePdfAction::class);
    try {
        $pdfBytes = $qrAction->execute($invoice, $org, $org->locale ?? 'fr');
        file_put_contents("{$outDir}/9-invoice-qr.pdf", $pdfBytes);
        echo "9. Invoice QR PDF ({$invoice->number}) ✓\n";
    } catch (Throwable $e) {
        echo "9. Invoice QR PDF FAILED: {$e->getMessage()}\n";
    }
} else {
    echo "9. Invoice QR PDF — no invoice found\n";
}

echo "\nAll PDFs saved to public/pdf-preview/\n";
$baseUrl = config('app.url');
echo "Browse at: {$baseUrl}/pdf-preview/\n";
