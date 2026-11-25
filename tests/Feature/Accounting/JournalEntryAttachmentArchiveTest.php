<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Accounting\Services\JournalEntryAttachmentService;
use App\Domains\Accounting\Services\LedgerService;
use App\Support\Contracts\ArchiveCustodyEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use TCPDF;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;
use ZipArchive;

class JournalEntryAttachmentArchiveTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->actAsOrg();
        Storage::fake('local');
    }

    public function test_export_links_each_original_to_its_posted_entry_and_preserves_earlier_versions(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $debit = $this->draft('UBS-DEBIT');
        $credit = $this->draft('UBS-CREDIT');
        $attachments = app(JournalEntryAttachmentService::class);
        $archives = app(ArchivePackageService::class);
        $invoice = $this->pdf('Invoice 35.65 CHF');
        $creditNote = $this->pdf('Credit 35.08 CHF');
        $withdrawn = $attachments->add($debit, UploadedFile::fake()->createWithContent('initial.pdf', $invoice), $this->user)['attachment'];
        $attachments->withdraw($debit, $withdrawn, $this->user);
        $attachments->add($credit, UploadedFile::fake()->createWithContent('credit.pdf', $creditNote), $this->user);

        $before = $archives->prepare($archives->request($this->organization, $year));
        $this->assertArrayNotHasKey('journal_entry_attachments', $before->snapshot);
        $this->assertSame([], $before->snapshot['issued_document_files']);
        $this->assertSame([], $before->snapshot['archive_access_events']);
        $beforeManifest = $before->manifest_sha256;
        app(LedgerService::class)->postDraft($debit);
        app(LedgerService::class)->postDraft($credit);
        $captured = $archives->request($this->organization, $year);
        $this->assertSame($captured->id, $archives->request($this->organization, $year)->id);
        $this->assertSame($before->version + 1, $captured->version);

        $correction = $this->pdf('Corrected invoice');
        $attachments->add($debit->refresh(), UploadedFile::fake()->createWithContent('corrected.pdf', $correction), $this->user);
        $archives->prepare($captured);
        $manifestHash = $captured->manifest_sha256;
        $later = $archives->prepare($archives->request($this->organization, $year));
        $this->assertSame($captured->version + 1, $later->version);
        $this->assertCount(2, $captured->snapshot['journal_entry_attachments']);

        $zipPath = $archives->export($later);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        try {
            $snapshot = json_decode($zip->getFromName('data/snapshot.json'), true, flags: JSON_THROW_ON_ERROR);
            $index = array_column($snapshot['journal_entry_attachments'], null, 'original_filename');
            $manifest = json_decode($zip->getFromName('manifest.json'), true, flags: JSON_THROW_ON_ERROR);
            $files = array_column($manifest['files'], null, 'path');
            $csv = $zip->getFromName('data/journal_entry_attachments.csv');
            $this->assertCount(3, $index);
            $this->assertCount(3, array_filter($snapshot['archive_access_events'], fn (array $event) => $event['action'] === 'journal_attachment.added'));
            $this->assertNotNull($index['initial.pdf']['deleted_at']);
            $this->assertNull($index['corrected.pdf']['deleted_at']);
            foreach (['initial.pdf' => [$debit, $invoice], 'credit.pdf' => [$credit, $creditNote], 'corrected.pdf' => [$debit, $correction]] as $name => [$entry, $bytes]) {
                $row = $index[$name];
                $this->assertSame($entry->id, $row['journal_entry_id']);
                $this->assertSame($entry->reference, $row['entry_reference']);
                $this->assertStringContainsString($entry->reference, $csv);
                $this->assertStringContainsString($row['package_path'], $csv);
                $this->assertSame($bytes, $zip->getFromName($row['package_path']));
                $this->assertSame(hash('sha256', $bytes), $files[$row['package_path']]['sha256']);
                $this->assertSame('original', $files[$row['package_path']]['role']);
            }
        } finally {
            $zip->close();
            unlink($zipPath);
        }

        $this->assertTrue($archives->verify($captured)['valid']);
        $this->assertSame($manifestHash, $archives->prepare($captured)->manifest_sha256);
        $this->assertTrue($archives->verify($before)['valid']);
        $this->assertSame($beforeManifest, $before->fresh()->manifest_sha256);
    }

    public function test_hosted_proof_capture_binds_the_journal_original_and_custody_enrollment_in_one_snapshot(): void
    {
        config(['features.saas' => true, 'archiving.proofs_enabled' => true]);
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $entry = $this->draft('JOURNAL-PROOF');
        $bytes = $this->pdf('Posted journal original');
        $attachment = app(JournalEntryAttachmentService::class)->add($entry, UploadedFile::fake()->createWithContent('original.pdf', $bytes), $this->user)['attachment'];
        app(LedgerService::class)->postDraft($entry);
        $enrollment = (string) Str::uuid();
        $this->mock(ArchiveCustodyEnrollment::class)->shouldReceive('activeId')->with($this->organization->id)->andReturn($enrollment);

        $packages = app(ArchivePackageService::class);
        $package = $packages->request($this->organization, $year, archivalPdf: true);
        $snapshot = $package->snapshot;
        $this->assertSame($enrollment, $snapshot['custody_enrollment'][0]['id']);
        $this->assertSame($this->organization->id, $snapshot['custody_enrollment'][0]['organization_id']);
        $link = $snapshot['journal_entry_attachments'][0];
        $this->assertSame($attachment->id, $link['id']);
        $this->assertSame($entry->reference, $link['entry_reference']);
        $this->assertSame($snapshot['original_sources'][0]['package_path'], $link['package_path']);
        $this->assertSame(hash('sha256', $bytes), $link['checksum_sha256']);
        $this->assertSame(hash('sha256', ArchivePackageService::json($snapshot)), $package->snapshot_sha256);
        $this->assertSame($package->id, $packages->request($this->organization, $year, archivalPdf: true)->id);
    }

    public function test_capture_excludes_other_tenants_years_and_originals_left_by_deleted_drafts(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        FiscalYear::factory()->for($this->organization)->expired()->create(['name' => '2025', 'start_date' => '2025-01-01', 'end_date' => '2025-12-31']);
        $organization = $this->organization;
        $attachments = app(JournalEntryAttachmentService::class);
        $archives = app(ArchivePackageService::class);
        $ledger = app(LedgerService::class);
        $upload = fn () => UploadedFile::fake()->createWithContent('document.pdf', $this->pdf('Supporting document'));
        $old = $this->draft('LAST-YEAR', '2025-12-31');
        $attachments->add($old, $upload(), $this->user);
        $ledger->postDraft($old);
        $deleted = $this->draft('DELETED');
        $attachment = $attachments->add($deleted, $upload(), $this->user)['attachment'];
        $attachments->withdraw($deleted, $attachment, $this->user);
        $ledger->deleteDraft($deleted);
        $this->assertDatabaseHas('issued_document_files', ['id' => $attachment->issued_document_file_id]);

        $this->setUpOrganization();
        $this->actAsOrg();
        FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $other = $this->draft('OTHER-TENANT');
        $attachments->add($other, $upload(), $this->user);
        $ledger->postDraft($other);
        $package = $archives->prepare($archives->request($organization, $year));
        $this->assertArrayNotHasKey('journal_entry_attachments', $package->snapshot);
        $this->assertSame([], $package->snapshot['issued_document_files']);
        $this->assertSame([], $package->snapshot['original_sources']);
        $this->assertSame([], $package->snapshot['archive_access_events']);
        $this->assertTrue($archives->verify($package)['valid']);
    }

    public function test_original_issued_before_the_fiscal_year_exists_is_required_for_preparation(): void
    {
        $entry = $this->draft('LEGACY-PERIOD');
        $bytes = $this->pdf('Original evidence');
        $attachment = app(JournalEntryAttachmentService::class)
            ->add($entry, UploadedFile::fake()->createWithContent('evidence.pdf', $bytes), $this->user)['attachment'];
        $issued = $attachment->issuedDocumentFile;
        $this->assertNull($issued->fiscal_year_id);
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        app(LedgerService::class)->postDraft($entry);
        $archives = app(ArchivePackageService::class);
        $package = $archives->request($this->organization, $year);
        $this->assertSame($issued->id, $package->snapshot['issued_document_files'][0]['id']);
        $snapshotHash = $package->snapshot_sha256;
        $disk = Storage::disk('local');
        $disk->delete($issued->storage_path);
        try {
            $archives->prepare($package);
            $this->fail('Preparation must report a missing journal supporting document.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Required original document is missing: issued_document/'.$issued->id, $exception->getMessage());
        }
        $this->assertNull($package->fresh()->manifest_sha256);
        $disk->put($issued->storage_path, $bytes);
        $archives->prepare($package);
        $this->assertSame($snapshotHash, $package->snapshot_sha256);
        $this->assertTrue($archives->verify($package)['valid']);
    }

    private function draft(string $reference, string $date = '2026-11-01'): JournalEntry
    {
        $bank = Account::firstOrCreate(['organization_id' => $this->organization->id, 'code' => '1020'], ['name' => 'Bank', 'type' => AccountType::Asset]);
        $expense = Account::firstOrCreate(['organization_id' => $this->organization->id, 'code' => '6000'], ['name' => 'Expense', 'type' => AccountType::Expense]);

        return app(LedgerService::class)->createDraft($this->organization->id, new JournalEntryData(
            date: $date, reference: $reference, description: $reference,
            lines: [new JournalLineData(accountId: (string) $bank->id, debit: '1', credit: '0'), new JournalLineData(accountId: (string) $expense->id, debit: '0', credit: '1')],
        ));
    }

    private function pdf(string $text): string
    {
        $pdf = new TCPDF;
        $pdf->AddPage();
        $pdf->Write(0, $text);

        return $pdf->Output('', 'S');
    }
}
