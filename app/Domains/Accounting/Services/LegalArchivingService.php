<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\DTOs\FiscalYearPeriod;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Payroll\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * Archives accounting documents (invoices, expenses, journal entries, salary slips)
 * for Swiss legal retention compliance (10-year CO requirement).
 */
class LegalArchivingService
{
    private const RETENTION_YEARS = 10;

    public function __construct(
        private readonly FiscalYearService $fiscalYears,
        private readonly GenerateArchivePdfAction $pdfAction,
    ) {}

    // ──────────────────────────────────────────────────────────────
    //  Single Document Archiving
    // ──────────────────────────────────────────────────────────────

    /**
     * Archive a single document (invoice, expense, journal_entry, salary_slip).
     *
     * The document is serialised as JSON and stored at an append-only path.
     * A SHA-256 checksum is computed and saved for later integrity verification.
     */
    public function archiveDocument(
        Model $document,
        string $documentType,
        ?FiscalYearPeriod $period = null,
    ): LegalArchive {
        $orgId = $document->getAttribute('organization_id');
        $id = (string) $document->getKey();
        $year = $period !== null ? Carbon::parse($period->fromDate)->year : (int) now()->year;
        $periodKey = $period === null
            ? (string) $year
            : ($period->fiscalYearId ?? (string) $year);

        // Determine fiscal year from document date if present
        foreach (['issue_date', 'date', 'period_year', 'created_at'] as $dateField) {
            if (isset($document->{$dateField})) {
                $val = $document->{$dateField};
                $year = $period !== null
                    ? $year
                    : (is_int($val) ? $val : Carbon::parse($val)->year);
                break;
            }
        }

        $payload = json_encode($document->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($payload === false) {
            throw new \RuntimeException('Failed to encode document for archiving');
        }
        $checksum = hash('sha256', $payload);

        $latest = LegalArchive::query()
            ->where('organization_id', $orgId)
            ->where('document_type', $documentType)
            ->where('document_id', $id)
            ->orderByDesc('version')
            ->first();
        $version = ($latest === null ? 0 : $latest->version) + 1;
        $filename = $id.($version > 1 ? "-v{$version}" : '').'.json';
        $relativePath = "archives/{$orgId}/{$periodKey}/{$documentType}/{$filename}";

        // Append-only: do not overwrite an existing archive.
        if (! Storage::exists($relativePath)) {
            Storage::put($relativePath, $payload);
        }

        $now = now();

        $archiveValues = [
            'fiscal_year' => $year,
            'checksum_sha256' => $checksum,
            'storage_path' => $relativePath,
            'archived_at' => $now,
            'expires_at' => $now->copy()->addYears(self::RETENTION_YEARS),
            'verified_at' => null,
        ];

        if ($period !== null) {
            $archiveValues['fiscal_year_id'] = $period->fiscalYearId;
        }

        return LegalArchive::create([
            'organization_id' => $orgId,
            'document_type' => $documentType,
            'document_id' => $id,
            'version' => $version,
            ...$archiveValues,
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  Integrity Verification
    // ──────────────────────────────────────────────────────────────

    /**
     * Re-compute the SHA-256 hash and compare with the stored checksum.
     */
    public function verifyIntegrity(LegalArchive $archive): bool
    {
        if (! Storage::exists($archive->storage_path)) {
            return false;
        }

        $contents = Storage::get($archive->storage_path);
        $current = hash('sha256', $contents);
        $ok = hash_equals($archive->checksum_sha256, $current);

        if ($ok) {
            $archive->update(['verified_at' => now()]);
        }

        return $ok;
    }

    // ──────────────────────────────────────────────────────────────
    //  Bulk Archiving
    // ──────────────────────────────────────────────────────────────

    /**
     * Archive all relevant documents for a closed fiscal year.
     *
     * Called automatically from YearEndClosingAction and via the CLI command.
     */
    public function archiveFiscalYear(
        string $orgId,
        string|int $year,
        ?string $fiscalYearId = null,
        bool $preservePreviousVersion = false,
    ): void {
        $organization = Organization::findOrFail($orgId);
        $period = $this->fiscalYears->resolvePeriod(
            $organization,
            $fiscalYearId,
            is_numeric((string) $year) ? (int) $year : null,
        );
        $lock = Cache::lock(
            "archive:{$orgId}:".($period->fiscalYearId ?? $period->label),
            600,
        );

        $lock->block(10);

        try {
            $invoices = Invoice::where('organization_id', $orgId)
                ->whereBetween('issue_date', [$period->fromDate, $period->toDate]);
            if (! $preservePreviousVersion) {
                $invoices->whereNull('archived_at');
            }
            $invoices
                ->each(function (Invoice $doc) use ($period): void {
                    $this->archiveDocument($doc, 'invoice', $period);
                    $doc->update(['archived_at' => now()]);
                });

            $expenses = Expense::where('organization_id', $orgId)
                ->whereBetween('date', [$period->fromDate, $period->toDate]);
            if (! $preservePreviousVersion) {
                $expenses->whereNull('archived_at');
            }
            $expenses
                ->each(function (Expense $doc) use ($period): void {
                    $this->archiveDocument($doc, 'expense', $period);
                    $doc->update(['archived_at' => now()]);
                });

            $journalEntries = JournalEntry::where('organization_id', $orgId)
                ->whereBetween('date', [$period->fromDate, $period->toDate]);
            if (! $preservePreviousVersion) {
                $journalEntries->whereNull('archived_at');
            }
            $journalEntries
                ->each(function (JournalEntry $doc) use ($period): void {
                    $this->archiveDocument($doc, 'journal_entry', $period);
                    $doc->update(['archived_at' => now()]);
                });

            $startKey = (int) Carbon::parse($period->fromDate)->format('Ym');
            $endKey = (int) Carbon::parse($period->toDate)->format('Ym');

            $salarySlips = SalarySlip::with('employee')
                ->where('organization_id', $orgId)
                ->whereRaw('(period_year * 100 + period_month) between ? and ?', [$startKey, $endKey]);
            if (! $preservePreviousVersion) {
                $salarySlips->whereNull('archived_at');
            }
            $salarySlips
                ->each(function (SalarySlip $doc) use ($period): void {
                    $this->archiveDocument($doc, 'salary_slip', $period);
                    $doc->update(['archived_at' => now()]);
                });

            if ($preservePreviousVersion) {
                try {
                    $this->pdfAction->execute(
                        $orgId,
                        $period->label,
                        $period->fiscalYearId,
                        $preservePreviousVersion,
                    );
                } catch (\Throwable $e) {
                    Log::warning('Failed to generate archive PDFs', [
                        'organization_id' => $orgId,
                        'fiscal_year' => $period->label,
                        'exception' => $e->getMessage(),
                    ]);
                }
            }

            Log::info('Fiscal-year archive generated', [
                'organization_id' => $orgId,
                'fiscal_year_id' => $period->fiscalYearId,
                'fiscal_year' => $period->label,
                'from_date' => $period->fromDate,
                'to_date' => $period->toDate,
            ]);
        } finally {
            $lock->release();
        }
    }
}
