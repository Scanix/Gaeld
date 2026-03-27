<?php

namespace App\Domains\Reporting\Services;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Accounting\Services\VatReportService;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

class AccountingExportService
{
    public function __construct(
        private LedgerService $ledgerService,
        private ReportingService $reportingService,
        private VatReportService $vatReportService,
    ) {}

    /**
     * Generate a complete accounting export ZIP for fiduciary handoff.
     *
     * @param  string  $orgId  Organization UUID
     * @param  string  $fiscalYear  Fiscal year (e.g. "2025")
     * @return string Absolute path to the generated ZIP file
     */
    public function generateExport(string $orgId, string $fiscalYear): string
    {
        $year = (int) $fiscalYear;
        $fromDate = "{$year}-01-01";
        $toDate = "{$year}-12-31";

        Storage::disk('local')->makeDirectory('exports');

        $tmpDir = sys_get_temp_dir().'/gaeld-export-'.uniqid();
        mkdir($tmpDir, 0700, true);
        mkdir($tmpDir.'/vat-reports', 0700, true);

        try {
            $org = Organization::findOrFail($orgId);

            $this->buildChartOfAccounts($tmpDir, $orgId);
            $this->buildJournalEntries($tmpDir, $orgId, $fromDate, $toDate);
            $this->buildTrialBalance($tmpDir, $orgId, $toDate);
            $this->buildProfitAndLoss($tmpDir, $org, $fromDate, $toDate);
            $this->buildBalanceSheet($tmpDir, $org, $toDate);
            $this->buildVatReports($tmpDir, $org, $year);
            $this->buildInvoicesCsv($tmpDir, $orgId, $fromDate, $toDate);
            $this->buildExpensesCsv($tmpDir, $orgId, $fromDate, $toDate);

            $zipFilename = "exports/accounting-{$orgId}-{$fiscalYear}.zip";
            $zipPath = Storage::disk('local')->path($zipFilename);

            $this->createZip($zipPath, $tmpDir);

            return $zipPath;
        } finally {
            $this->rmdirRecursive($tmpDir);
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Individual file builders
    // ──────────────────────────────────────────────────────────────

    private function buildChartOfAccounts(string $tmpDir, string $orgId): void
    {
        $accounts = Account::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_active', true)
            ->orderBy('code')
            ->get();

        $this->writeCsv(
            $tmpDir.'/chart-of-accounts.csv',
            ['Code', 'Name', 'Type', 'Description'],
            $accounts->map(fn (Account $a) => [
                $a->code,
                $a->name,
                $a->type->value,
                $a->description ?? '',
            ])->toArray(),
        );
    }

    private function buildJournalEntries(string $tmpDir, string $orgId, string $fromDate, string $toDate): void
    {
        $entries = JournalEntry::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->where('is_posted', true)
            ->whereBetween('date', [$fromDate, $toDate])
            ->with('lines.account')
            ->orderBy('date')
            ->get();

        $rows = [];
        foreach ($entries as $entry) {
            foreach ($entry->lines as $line) {
                $rows[] = [
                    $entry->date->toDateString(),
                    $entry->reference,
                    $entry->description ?? '',
                    $line->account?->code ?? '',
                    $line->account?->name ?? '',
                    $line->debit,
                    $line->credit,
                ];
            }
        }

        $this->writeCsv(
            $tmpDir.'/journal-entries.csv',
            ['Date', 'Reference', 'Description', 'Account Code', 'Account Name', 'Debit', 'Credit'],
            $rows,
        );
    }

    private function buildTrialBalance(string $tmpDir, string $orgId, string $asOfDate): void
    {
        $rows = $this->ledgerService->trialBalance($orgId, $asOfDate);

        $this->writeCsv(
            $tmpDir.'/trial-balance.csv',
            ['Account Code', 'Account Name', 'Type', 'Debit', 'Credit'],
            array_map(fn (array $row) => [
                $row['account_code'],
                $row['account_name'],
                $row['account_type'] instanceof \BackedEnum ? $row['account_type']->value : (string) $row['account_type'],
                $row['debit'],
                $row['credit'],
            ], $rows),
        );
    }

    private function buildProfitAndLoss(string $tmpDir, Organization $org, string $fromDate, string $toDate): void
    {
        $report = $this->reportingService->profitAndLoss($org->id, $fromDate, $toDate);

        $pdfContent = Pdf::loadView('exports.profit-and-loss', [
            'organizationName' => $org->name,
            'period' => ['from' => $fromDate, 'to' => $toDate],
            'revenue' => $report['revenue'],
            'expenses' => $report['expenses'],
            'totalRevenue' => $report['total_revenue'],
            'totalExpenses' => $report['total_expenses'],
            'netProfit' => $report['net_profit'],
        ])->setPaper('A4', 'portrait')->output();

        file_put_contents($tmpDir.'/profit-and-loss.pdf', $pdfContent);
    }

    private function buildBalanceSheet(string $tmpDir, Organization $org, string $asOfDate): void
    {
        $report = $this->reportingService->balanceSheet($org->id, $asOfDate);

        $pdfContent = Pdf::loadView('exports.balance-sheet', [
            'organizationName' => $org->name,
            'asOfDate' => $asOfDate,
            'assets' => $report['assets'],
            'liabilities' => $report['liabilities'],
            'equity' => $report['equity'],
        ])->setPaper('A4', 'portrait')->output();

        file_put_contents($tmpDir.'/balance-sheet.pdf', $pdfContent);
    }

    private function buildVatReports(string $tmpDir, Organization $org, int $year): void
    {
        $quarters = [
            'Q1' => ["{$year}-01-01", "{$year}-03-31"],
            'Q2' => ["{$year}-04-01", "{$year}-06-30"],
            'Q3' => ["{$year}-07-01", "{$year}-09-30"],
            'Q4' => ["{$year}-10-01", "{$year}-12-31"],
        ];

        foreach ($quarters as $quarter => [$fromDate, $toDate]) {
            $report = $this->vatReportService->generate($org->id, $fromDate, $toDate);

            $pdfContent = Pdf::loadView('exports.vat-report', [
                'organizationName' => $org->name,
                'report' => $report,
            ])->setPaper('A4', 'portrait')->output();

            file_put_contents($tmpDir.'/vat-reports/'.$quarter.'.pdf', $pdfContent);
        }
    }

    private function buildInvoicesCsv(string $tmpDir, string $orgId, string $fromDate, string $toDate): void
    {
        $invoices = Invoice::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereBetween('issue_date', [$fromDate, $toDate])
            ->orderBy('issue_date')
            ->get();

        $this->writeCsv(
            $tmpDir.'/invoices.csv',
            ['Number', 'Status', 'Type', 'Issue Date', 'Due Date', 'Subtotal', 'VAT', 'Total', 'Currency'],
            $invoices->map(fn (Invoice $i) => [
                $i->number ?? '',
                $i->status->value,
                $i->type->value,
                $i->issue_date->toDateString(),
                $i->due_date->toDateString(),
                $i->subtotal,
                $i->vat_amount,
                $i->total,
                $i->currency,
            ])->toArray(),
        );
    }

    private function buildExpensesCsv(string $tmpDir, string $orgId, string $fromDate, string $toDate): void
    {
        $expenses = Expense::withoutGlobalScope('organization')
            ->where('organization_id', $orgId)
            ->whereBetween('date', [$fromDate, $toDate])
            ->orderBy('date')
            ->get();

        $this->writeCsv(
            $tmpDir.'/expenses.csv',
            ['Date', 'Category', 'Description', 'Vendor', 'Amount', 'VAT', 'Currency', 'Status'],
            $expenses->map(fn (Expense $e) => [
                $e->date->toDateString(),
                $e->category,
                $e->description ?? '',
                $e->vendor ?? '',
                $e->amount,
                $e->vat_amount,
                $e->currency,
                $e->status->value,
            ])->toArray(),
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Write rows to a CSV file with UTF-8 BOM for Excel compatibility.
     *
     * @param  string[]  $headers
     * @param  array<int, array>  $rows
     */
    private function writeCsv(string $path, array $headers, array $rows): void
    {
        $handle = fopen($path, 'w');
        fwrite($handle, "\xEF\xBB\xBF"); // UTF-8 BOM
        fputcsv($handle, $headers, ';');
        foreach ($rows as $row) {
            fputcsv($handle, $row, ';');
        }
        fclose($handle);
    }

    private function createZip(string $zipPath, string $sourceDir): void
    {
        $zip = new ZipArchive;
        $zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($sourceDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::LEAVES_ONLY,
        );

        foreach ($iterator as $file) {
            $relativePath = str_replace($sourceDir.'/', '', $file->getRealPath());
            $zip->addFile($file->getRealPath(), $relativePath);
        }

        $zip->close();
    }

    private function rmdirRecursive(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST,
        );

        foreach ($iterator as $item) {
            $item->isDir() ? rmdir($item->getRealPath()) : unlink($item->getRealPath());
        }

        rmdir($dir);
    }
}
