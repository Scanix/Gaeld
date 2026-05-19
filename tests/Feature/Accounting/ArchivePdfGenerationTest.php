<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\Models\LegalArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
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

    public function test_action_regenerates_when_forced(): void
    {
        $year = 2024;
        $action = app(GenerateArchivePdfAction::class);

        $action->execute($this->organization->id, $year);
        $results = $action->execute($this->organization->id, $year, force: true);

        foreach ($results as $r) {
            $this->assertTrue($r['regenerated'], "Expected {$r['type']} to be regenerated with force");
        }
    }

    public function test_download_pdf_endpoint_returns_pdf_response(): void
    {
        $response = $this->actAsOrg()->get('/accounting/archives/year/2024/pdf/pnl');

        $response->assertStatus(200);
        $response->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF-', $response->streamedContent());
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

        // Tamper with stored file to verify it gets rewritten with the same content
        Storage::put($original->storage_path, 'tampered');

        $response = $this->actAsOrg()
            ->post("/accounting/archives/year/{$year}/regenerate-pdfs");

        $response->assertRedirect();

        $content = Storage::get($original->storage_path);
        $this->assertStringStartsWith('%PDF-', $content);
    }
}
