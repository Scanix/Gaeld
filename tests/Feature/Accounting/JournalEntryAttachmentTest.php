<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryAttachment;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Support\Contracts\OrganizationQuotaResolver;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Security\SecurityTestCase;

class JournalEntryAttachmentTest extends SecurityTestCase
{
    private const PDF = "%PDF-1.4\n% Stored original\n%%EOF\n";

    private JournalEntry $entry;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ownerA->update(['onboarding_completed_at' => now()]);
        $this->ownerB->update(['onboarding_completed_at' => now()]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->actingAs($this->ownerA)->withSession(['current_organization_id' => $this->orgA->id]);
        Storage::fake('local');
        $this->entry = JournalEntry::create(['organization_id' => $this->orgA->id, 'reference' => 'FIRST', 'date' => '2026-05-12', 'is_posted' => false]);
    }

    public function test_withdrawal_and_reattachment_retain_one_original_and_one_storage_charge(): void
    {
        $this->upload()->assertSessionHas('success', __('app.journal_attachment_added'));
        $link = $this->entry->attachments()->sole();
        $pdf = $link->issuedDocumentFile;
        $this->upload()->assertSessionHas('success', __('app.journal_attachment_duplicate'));
        $url = fn (string $action) => route('accounting.journal-entries.attachments.'.$action, [$this->entry, $link]);
        $this->delete($url('destroy'))->assertRedirect();
        $this->get($url('show'))->assertNotFound();
        $this->assertSame(self::PDF, Storage::disk('local')->get($pdf->storage_path));
        $this->upload()->assertSessionHas('success', __('app.journal_attachment_restored'));
        $this->assertSame($link->id, $this->entry->attachments()->sole()->id);
        $this->assertDatabaseCount('issued_document_files', 1);
        $this->assertSame(strlen(self::PDF), app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
        $this->assertSame(self::PDF, $this->get($url('show'))->assertOk()->baseResponse->getContent());
        $this->assertSame(self::PDF, $this->get($url('download'))->assertDownload()->baseResponse->getContent());
        $this->assertEqualsCanonicalizing(['added', 'withdrawn', 'restored', 'viewed', 'downloaded'], ArchiveAccessEvent::query()
            ->where('metadata->attachment_id', $link->id)->where('actor_id', $this->ownerA->id)->pluck('action')
            ->map(fn (string $event) => substr($event, strlen('journal_attachment.')))->all());
    }

    public function test_each_route_requires_a_matching_entry_and_organization(): void
    {
        $this->upload()->assertSessionHasNoErrors();
        $link = $this->entry->attachments()->sole();
        $other = JournalEntry::create(['organization_id' => $this->orgA->id, 'reference' => 'SECOND', 'date' => '2026-05-12', 'is_posted' => false]);
        foreach (['download', 'show', 'destroy'] as $action) {
            $uri = route('accounting.journal-entries.attachments.'.$action, [$other, $link]);
            ($action === 'destroy' ? $this->delete($uri) : $this->get($uri))->assertNotFound();
        }

        app(CurrentOrganization::class)->set($this->orgB);
        $this->actingAs($this->ownerB)->withSession(['current_organization_id' => $this->orgB->id]);
        $this->assertDenied($this->upload());
        foreach (['download', 'show', 'destroy'] as $action) {
            $uri = route('accounting.journal-entries.attachments.'.$action, [$this->entry, $link]);
            $this->assertDenied($action === 'destroy' ? $this->delete($uri) : $this->get($uri));
        }
    }

    public function test_posted_and_closed_period_rules_are_checked_at_upload_and_withdrawal(): void
    {
        $this->entry->update(['is_posted' => true]);
        $year = FiscalYear::factory()->for($this->orgA)->expired()->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->upload()->assertSessionHasNoErrors();
        $link = $this->entry->attachments()->sole();
        $this->delete(route('accounting.journal-entries.attachments.destroy', [$this->entry, $link]))->assertForbidden();
        $year->update(['status' => 'closed', 'locked_at' => now()]);
        $this->upload()->assertForbidden();
        $this->get(route('accounting.journal-entries.attachments.download', [$this->entry, $link]))->assertOk();
    }

    public function test_an_accounting_viewer_can_read_existing_documents_but_cannot_change_the_links(): void
    {
        $this->upload()->assertSessionHasNoErrors();
        $attachment = $this->entry->attachments()->sole();
        $viewer = $this->createUserInOrg($this->orgA, 'viewer');
        $viewer->update(['onboarding_completed_at' => now()]);
        $this->actingAs($viewer)->withSession(['current_organization_id' => $this->orgA->id]);
        $this->get(route('accounting.journal-entries.attachments.show', [$this->entry, $attachment]))->assertOk();
        $this->upload()->assertForbidden();
        $this->delete(route('accounting.journal-entries.attachments.destroy', [$this->entry, $attachment]))->assertForbidden();
        $this->assertNull($attachment->fresh()->deleted_at);
    }

    public function test_retry_removes_only_unreferenced_bytes_from_the_previous_attempt(): void
    {
        $hash = hash('sha256', self::PDF);
        $interrupted = 'archives/issued/'.$this->orgA->id.'/journal_entry_attachment/'.$this->entry->id.'/'.$hash.'/4a420efa-2c3f-4f7a-af38-d67826d4b282.pdf';
        Storage::disk('local')->put($interrupted, '%PDF-1.4');
        $this->upload()->assertSessionHasNoErrors();
        $original = $this->entry->attachments()->sole()->issuedDocumentFile->storage_path;
        Storage::disk('local')->assertMissing($interrupted);
        $this->assertSame([$original], Storage::disk('local')->allFiles());
        $this->upload()->assertSessionHas('success', __('app.journal_attachment_duplicate'));
        $this->assertSame(self::PDF, Storage::disk('local')->get($original));
        $this->assertSame(strlen(self::PDF), app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
    }

    public function test_legacy_closed_calendar_year_and_archived_drafts_are_read_only(): void
    {
        $this->orgA->closeFiscalYear(2026);
        $this->upload()->assertForbidden();
        $this->orgA->reopenFiscalYear(2026);
        $this->entry->update(['archived_at' => now()]);
        $this->upload()->assertForbidden();
    }

    public function test_pdf_content_and_size_are_validated_before_any_file_is_retained(): void
    {
        $this->upload('not a PDF')->assertSessionHasErrors('file');
        $this->upload("%PDF-1.7\ntruncated")->assertSessionHasErrors('file');
        config(['uploads.max_size.document' => 1]);
        $this->upload("%PDF-1.4\n".str_repeat(' ', 1024)."\n%%EOF")->assertSessionHasErrors('file');
        $this->assertDatabaseCount('issued_document_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_quota_refusal_is_shown_on_the_file_field_and_writes_nothing(): void
    {
        $quotas = $this->mock(OrganizationQuotaResolver::class);
        $quotas->shouldReceive('maxOrganizations', 'maxUsers', 'maxOcrScansPerDay', 'maxOcrScansPerMonth', 'maxInvoicesPerMonth')->andReturn(-1);
        $quotas->shouldReceive('maxStorageBytes')->andReturn(1);
        $this->upload()->assertSessionHasErrors('file');
        $this->assertDatabaseCount('issued_document_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_valid_pdf_under_an_extensionless_or_misleading_name_downloads_as_a_pdf(): void
    {
        $this->post(route('accounting.journal-entries.attachments.store', $this->entry), [
            'file' => UploadedFile::fake()->createWithContent('receipt.html', self::PDF)->mimeType('application/pdf'),
        ])->assertSessionHasNoErrors();
        $attachment = $this->entry->attachments()->sole();
        $this->assertSame('receipt.html', $attachment->original_filename);
        $parameters = [$this->entry, $attachment];
        $this->get(route('accounting.journal-entries.attachments.download', $parameters))
            ->assertDownload('receipt.html.pdf')->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    }

    public function test_a_rolled_back_attachment_leaves_no_file_evidence_charge_or_audit(): void
    {
        DB::beginTransaction();
        $this->upload()->assertSessionHasNoErrors();
        $this->assertCount(1, Storage::disk('local')->allFiles());
        DB::rollBack();
        $this->assertDatabaseCount('issued_document_files', 0);
        $this->assertDatabaseCount('journal_entry_attachments', 0);
        $this->assertDatabaseCount('archive_access_events', 0);
        $this->assertSame(0, app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
        $this->assertSame([], Storage::disk('local')->allFiles());
        $this->upload()->assertSessionHas('success', __('app.journal_attachment_added'));
    }

    public function test_existing_altered_evidence_is_neither_served_nor_replaced_on_replay(): void
    {
        $this->upload()->assertSessionHasNoErrors();
        $link = $this->entry->attachments()->sole();
        Storage::disk('local')->put($link->issuedDocumentFile->storage_path, 'changed');
        $this->get(route('accounting.journal-entries.attachments.show', [$this->entry, $link]))->assertConflict();
        $this->upload()->assertConflict();
        $this->assertSame('changed', Storage::disk('local')->get($link->issuedDocumentFile->storage_path));
        $this->assertSame(1, JournalEntryAttachment::count());
    }

    private function upload(string $bytes = self::PDF): TestResponse
    {
        return $this->post(route('accounting.journal-entries.attachments.store', $this->entry), [
            'file' => UploadedFile::fake()->createWithContent('Facture d’été.pdf', $bytes),
        ]);
    }
}
