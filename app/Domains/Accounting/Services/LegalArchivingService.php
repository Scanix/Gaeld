<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Payroll\Models\SalarySlip;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
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
        private readonly ?GenerateArchivePdfAction $pdfAction = null,
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
    public function archiveDocument(Model $document, string $documentType): LegalArchive
    {
        $orgId = $document->getAttribute('organization_id');
        $id = (string) $document->getKey();
        $year = (int) now()->year;

        // Determine fiscal year from document date if present
        foreach (['issue_date', 'date', 'period_year', 'created_at'] as $dateField) {
            if (isset($document->{$dateField})) {
                $val = $document->{$dateField};
                $year = is_int($val) ? $val : Carbon::parse($val)->year;
                break;
            }
        }

        $payload = json_encode($document->toArray(), JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
        if ($payload === false) {
            throw new \RuntimeException('Failed to encode document for archiving');
        }
        $checksum = hash('sha256', $payload);

        $relativePath = "archives/{$orgId}/{$year}/{$documentType}/{$id}.json";

        // Append-only: do not overwrite an existing archive
        if (! Storage::exists($relativePath)) {
            Storage::put($relativePath, $payload);
        }

        $now = now();

        return LegalArchive::updateOrCreate(
            [
                'organization_id' => $orgId,
                'document_type' => $documentType,
                'document_id' => $id,
            ],
            [
                'fiscal_year' => $year,
                'checksum_sha256' => $checksum,
                'storage_path' => $relativePath,
                'archived_at' => $now,
                'expires_at' => $now->copy()->addYears(self::RETENTION_YEARS),
                'verified_at' => null,
            ]
        );
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
    public function archiveFiscalYear(string $orgId, int $year): void
    {
        // Invoices
        Invoice::where('organization_id', $orgId)
            ->whereYear('issue_date', $year)
            ->whereNull('archived_at')
            ->each(function ($doc) {
                $this->archiveDocument($doc, 'invoice');
                $doc->update(['archived_at' => now()]);
            });

        // Expenses
        Expense::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->whereNull('archived_at')
            ->each(function ($doc) {
                $this->archiveDocument($doc, 'expense');
                $doc->update(['archived_at' => now()]);
            });

        // Journal entries
        JournalEntry::where('organization_id', $orgId)
            ->whereYear('date', $year)
            ->whereNull('archived_at')
            ->each(function ($doc) {
                $this->archiveDocument($doc, 'journal_entry');
                $doc->update(['archived_at' => now()]);
            });

        // Salary slips
        SalarySlip::with('employee')
            ->where('organization_id', $orgId)
            ->where('period_year', $year)
            ->whereNull('archived_at')
            ->each(function ($doc) {
                $this->archiveDocument($doc, 'salary_slip');
                $doc->update(['archived_at' => now()]);
            });

        // Generate PDF artefacts for Swiss tax filing (CO art. 957a).
        // JSON archive remains the source of truth — PDF failure must not
        // abort the closing flow.
        if ($this->pdfAction !== null) {
            try {
                $this->pdfAction->execute($orgId, $year);
            } catch (\Throwable $e) {
                Log::warning('Failed to generate archive PDFs', [
                    'organization_id' => $orgId,
                    'fiscal_year' => $year,
                    'exception' => $e->getMessage(),
                ]);
            }
        }
    }
}
