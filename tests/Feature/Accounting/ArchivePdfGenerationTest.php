<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Phase 6: Per-fiscal-year PDF archive generation
 * (P&L, balance sheet, general journal) for Swiss tax filing.
 */
class ArchivePdfGenerationTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_action_generates_three_pdfs_with_checksums(): void
    {
        $year = 2024;

        $results = app(GenerateArchivePdfAction::class)->execute($this->organization->id, $year);

        $this->assertCount(3, $results);

        foreach (['pdf_pnl', 'pdf_balance_sheet', 'pdf_journal'] as $documentType) {
            $archive = LegalArchive::where('organization_id', $this->organization->id)
                ->where('document_type', $documentType)
                ->where('fiscal_year', $year)
                ->first();

            $this->assertNotNull($archive, "Missing archive row for {$documentType}");
            $this->assertSame(64, strlen($archive->checksum_sha256));
            $this->assertSame("pdf-{$year}", $archive->document_id);
            $this->assertSame(1, $archive->version);
            $this->assertTrue(Storage::exists($archive->storage_path), "PDF not written for {$documentType}");

            $content = Storage::get($archive->storage_path);
            $this->assertStringStartsWith('%PDF-', $content);
            $this->assertSame(hash('sha256', $content), $archive->checksum_sha256);
        }
    }

    public function test_action_is_idempotent_within_cooldown(): void
    {
        $year = 2024;
        $action = app(GenerateArchivePdfAction::class);

        $action->execute($this->organization->id, $year);

        $firstChecksum = LegalArchive::where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->value('checksum_sha256');

        $results = $action->execute($this->organization->id, $year);

        foreach ($results as $r) {
            $this->assertFalse($r['regenerated'], "Expected {$r['type']} to be skipped within cooldown");
        }

        $this->assertSame(
            $firstChecksum,
            LegalArchive::where('organization_id', $this->organization->id)
                ->where('document_type', 'pdf_pnl')
                ->value('checksum_sha256'),
        );
    }

    public function test_action_serializes_pdf_generation_with_a_period_lock(): void
    {
        Cache::spy();

        app(GenerateArchivePdfAction::class)->execute($this->organization->id, 2024);

        Cache::shouldHaveReceived('lock')
            ->once()
            ->with("archive-pdf:{$this->organization->id}:2024", 600);
    }

    public function test_action_regenerates_when_forced(): void
    {
        $year = 2024;
        $action = app(GenerateArchivePdfAction::class);

        $action->execute($this->organization->id, $year);

        // Delete the stored files so the seal-protection guard doesn't trigger.
        foreach (['pnl', 'balance-sheet', 'journal'] as $slug) {
            Storage::delete("archives/{$this->organization->id}/{$year}/pdf/{$slug}-{$year}.pdf");
        }

        $results = $action->execute($this->organization->id, $year, force: true);

        foreach ($results as $r) {
            $this->assertTrue($r['regenerated'], "Expected {$r['type']} to be regenerated with force");
        }
    }

    public function test_forced_regeneration_keeps_the_previous_pdf_version(): void
    {
        $year = 2024;
        $action = app(GenerateArchivePdfAction::class);

        $action->execute($this->organization->id, $year);
        $original = LegalArchive::query()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->where('version', 1)
            ->firstOrFail();

        $action->execute($this->organization->id, $year, force: true);

        $versions = LegalArchive::query()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->orderBy('version')
            ->get();
        $latest = $versions->last();

        $this->assertCount(2, $versions);
        $this->assertSame(1, $original->version);
        $this->assertSame(2, $latest->version);
        $this->assertNotSame($original->storage_path, $latest->storage_path);
        $this->assertTrue(Storage::exists($original->storage_path));
        $this->assertTrue(Storage::exists($latest->storage_path));
    }

    public function test_bundle_contains_only_the_latest_pdf_versions(): void
    {
        $year = 2024;
        $action = app(GenerateArchivePdfAction::class);

        $action->execute($this->organization->id, $year);
        $action->execute($this->organization->id, $year, force: true);

        $response = $this->actAsOrg()->get("/accounting/archives/year/{$year}/bundle");
        $response->assertOk();

        $tmp = tempnam(sys_get_temp_dir(), 'bundle-version-test-');
        file_put_contents($tmp, $response->streamedContent() ?: $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $entries = [];
        for ($index = 0; $index < $zip->numFiles; $index++) {
            $entries[] = $zip->getNameIndex($index);
        }
        $zip->close();
        @unlink($tmp);

        $this->assertSame([
            'balance-sheet-2024-v2.pdf',
            'journal-2024-v2.pdf',
            'pnl-2024-v2.pdf',
        ], $entries);
    }

    public function test_download_pdf_endpoint_returns_pdf_response(): void
    {
        // downloadPdf() now redirects to a temporary signed URL; follow it manually
        // so we can still assert streamedContent() on the final file response.
        $redirect = $this->actAsOrg()->get('/accounting/archives/year/2024/pdf/pnl');
        $redirect->assertRedirect();

        $signedUrl = $redirect->headers->get('Location');
        $response = $this->get($signedUrl);

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
    }

    public function test_explicit_fiscal_year_uses_stable_identity_for_pdf_artifacts(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->create([
            'name' => 'Migration year',
            'start_date' => '2024-01-01',
            'end_date' => '2025-06-30',
            'status' => FiscalYearStatus::Operative,
        ]);

        app(GenerateArchivePdfAction::class)->execute(
            $this->organization->id,
            '2024',
            $fiscalYear->id,
        );

        $archive = LegalArchive::query()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->firstOrFail();

        $this->assertSame("pdf-{$fiscalYear->id}", $archive->document_id);
        $this->assertStringContainsString("/{$fiscalYear->id}/pdf/", $archive->storage_path);

        $redirect = $this->actAsOrg()->get(
            "/accounting/archives/year/2024/pdf/pnl?fiscal_year_id={$fiscalYear->id}"
        );
        $redirect->assertRedirect();

        $this->get($redirect->headers->get('Location'))->assertOk();
    }

    public function test_download_pdf_endpoint_rejects_unknown_type(): void
    {
        $response = $this->actAsOrg()->get('/accounting/archives/year/2024/pdf/unknown');

        $response->assertStatus(404);
    }

    public function test_bundle_endpoint_returns_zip_with_three_pdfs(): void
    {
        $response = $this->actAsOrg()->get('/accounting/archives/year/2024/bundle');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/zip');

        $tmp = tempnam(sys_get_temp_dir(), 'bundle-test-');
        file_put_contents($tmp, $response->streamedContent() ?: $response->getContent());

        $zip = new \ZipArchive;
        $this->assertTrue($zip->open($tmp) === true);
        $this->assertSame(3, $zip->numFiles);
        $zip->close();
        @unlink($tmp);
    }

    public function test_regenerate_endpoint_forces_refresh(): void
    {
        $year = 2024;
        app(GenerateArchivePdfAction::class)->execute($this->organization->id, $year);

        $original = LegalArchive::where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->first();

        // Corrupt the file on disk (simulates storage bit-rot / accidental overwrite).
        // Recovery must append a new version and preserve the old evidence.
        Storage::put($original->storage_path, 'tampered');

        $response = $this->actAsOrg()
            ->post("/accounting/archives/year/{$year}/regenerate-pdfs");

        $response->assertRedirect();

        $latest = LegalArchive::query()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_pnl')
            ->orderByDesc('version')
            ->firstOrFail();

        $this->assertSame(2, $latest->version);
        $this->assertSame('tampered', Storage::get($original->storage_path));
        $this->assertStringStartsWith('%PDF-', Storage::get($latest->storage_path));
    }
}
