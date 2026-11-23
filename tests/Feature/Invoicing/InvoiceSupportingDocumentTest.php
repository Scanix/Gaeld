<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Api\Jobs\DispatchWebhookJob;
use App\Domains\Api\Models\Webhook;
use App\Domains\Invoicing\Actions\SyncInvoiceLinesAction;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Support\Contracts\OrganizationQuotaResolver;
use App\Support\Exceptions\FileUploadFailedException;
use App\Support\Services\FileUploadService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Testing\Fakes\QueueFake;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Security\SecurityTestCase;
use ZipArchive;

class InvoiceSupportingDocumentTest extends SecurityTestCase
{
    private const FIRST = "%PDF-1.4\n% Original supporting document\n%%EOF\n";

    private const SECOND = "%PDF-1.4\n% Revised supporting document\n%%EOF\n";

    private FiscalYear $year;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->ownerA->update(['onboarding_completed_at' => now()]);
        $this->ownerB->update(['onboarding_completed_at' => now()]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->actingAs($this->ownerA)->withSession(['current_organization_id' => $this->orgA->id]);
        $this->year = FiscalYear::factory()->for($this->orgA)->create([
            'start_date' => '2026-01-01', 'end_date' => '2026-12-31',
        ]);
    }

    public function test_the_owner_can_replace_and_recover_both_originals_and_another_organization_cannot_download_them(): void
    {
        $invoice = $this->createWithDocument();
        $firstPath = $invoice->justificatif_path;
        $download = route('invoices.justificatif.download', $invoice);
        $this->assertSame(self::FIRST, $this->get($download)->assertOk()->streamedContent());
        $this->edit($invoice)->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $this->assertSame($firstPath, $invoice->fresh()->justificatif_path);

        $this->edit($invoice, self::SECOND)->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));
        $this->assertNotSame($firstPath, $invoice->fresh()->justificatif_path);
        $this->assertSame(self::SECOND, $this->get($download)->assertOk()->streamedContent());
        $versions = IssuedDocumentFile::query()->where('document_type', 'supporting_invoice')->where('document_id', $invoice->id)->orderBy('version')->get();
        $this->assertSame([1, 2], $versions->pluck('version')->all());
        $this->assertSame([hash('sha256', self::FIRST), hash('sha256', self::SECOND)], $versions->pluck('checksum_sha256')->all());

        $service = app(ArchivePackageService::class);
        $package = $service->request($this->orgA, $this->year, $this->ownerA);
        $service->prepare($package);
        $this->assertTrue($service->verify($package)['valid']);
        $zipPath = $service->export($package);
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($zipPath));
        try {
            $recovered = [];
            foreach ($package->snapshot['original_sources'] as $source) {
                if ($source['type'] === 'issued_document') {
                    $recovered[] = $zip->getFromName($source['package_path']);
                }
            }
            $this->assertEqualsCanonicalizing([self::FIRST, self::SECOND], $recovered);
        } finally {
            $zip->close();
            unlink($zipPath);
        }

        app(CurrentOrganization::class)->set($this->orgB);
        $this->actingAs($this->ownerB)->withSession(['current_organization_id' => $this->orgB->id]);
        $this->assertDenied($this->get($download));
    }

    public function test_a_failed_edit_retains_the_previous_link_and_cleans_up_only_the_new_files(): void
    {
        $invoice = $this->createWithDocument();
        $firstPath = $invoice->justificatif_path;
        $disk = Storage::disk('local');
        $before = $disk->allFiles();
        $this->mock(SyncInvoiceLinesAction::class)->shouldReceive('replace')->once()->andThrow(new RuntimeException('Line persistence failed'));
        $this->withoutExceptionHandling();
        try {
            $this->edit($invoice, self::SECOND);
            $this->fail('The failed line update must abort the whole edit.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Line persistence failed', $exception->getMessage());
        }
        $this->assertSame($firstPath, $invoice->fresh()->justificatif_path);
        $this->assertSame(self::FIRST, $this->get(route('invoices.justificatif.download', $invoice))->assertOk()->streamedContent());
        $this->assertDatabaseCount('issued_document_files', 1);
        $this->assertEqualsCanonicalizing($before, $disk->allFiles());
    }

    public function test_a_failed_create_cleans_up_the_uploaded_original_and_its_evidence_copy(): void
    {
        $this->mock(SyncInvoiceLinesAction::class)->shouldReceive('create')->once()->andThrow(new RuntimeException('Line persistence failed'));
        $this->withoutExceptionHandling();
        try {
            $this->post(route('invoices.store'), $this->form(self::FIRST));
            $this->fail('The failed line creation must abort the invoice.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Line persistence failed', $exception->getMessage());
        }
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('issued_document_files', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_storage_quota_refusal_releases_the_monthly_invoice_slot(): void
    {
        $this->limits(storage: 0);
        $this->post(route('invoices.store'), $this->form(self::FIRST))->assertSessionHasErrors('document');

        $this->assertSame(0, $this->usedSlots());
        $this->assertSame(0, app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->post(route('invoices.store'), $this->form())->assertSessionHasNoErrors();
        $this->assertDatabaseCount('invoices', 1);
        $this->assertSame(1, $this->usedSlots());
    }

    public function test_disk_write_failure_releases_the_storage_and_monthly_invoice_reservations(): void
    {
        $this->limits();
        $this->mock(FileUploadService::class)->shouldReceive('store')->once()->andThrow(new FileUploadFailedException);
        $this->withoutExceptionHandling();
        try {
            $this->post(route('invoices.store'), $this->form(self::FIRST));
            $this->fail('The upload failure must abort creation.');
        } catch (FileUploadFailedException) {
            $this->assertDatabaseCount('invoices', 0);
        }

        $this->assertSame(0, $this->usedSlots());
        $this->assertSame(0, app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_a_post_commit_listener_failure_during_replacement_retains_the_committed_link_and_releases_only_the_old_live_file(): void
    {
        $this->limits();
        $invoice = $this->createWithDocument();
        $previous = $invoice->justificatif_path;
        Invoice::updated(fn () => DB::afterCommit(static fn () => throw new RuntimeException('Post-commit listener interrupted')));
        $this->edit($invoice, self::SECOND)->assertSessionHasNoErrors()->assertRedirect(route('invoices.show', $invoice));

        $this->assertNotSame($previous, $invoice->fresh()->justificatif_path);
        Storage::disk('local')->assertMissing($previous);
        $this->assertSame(self::SECOND, $this->get(route('invoices.justificatif.download', $invoice))->assertOk()->streamedContent());
        $this->assertSame(strlen(self::SECOND), app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));
        $this->assertSame(1, $this->usedSlots());
        $this->assertDatabaseCount('issued_document_files', 2);
    }

    public static function commitFailures(): array
    {
        return ['webhook queue push' => [false], 'earlier post-commit listener' => [true]];
    }

    #[DataProvider('commitFailures')]
    public function test_queue_unavailability_after_commit_preserves_the_draft_its_original_and_both_reservations(bool $interruptBeforeWebhook): void
    {
        $this->limits();
        config(['features.api_access' => true]);
        $webhook = Webhook::create([
            'organization_id' => $this->orgA->id, 'url' => 'https://93.184.216.34/hook',
            'secret' => 'synthetic-test-signing-key', 'events' => ['invoice.created'], 'is_active' => true,
        ]);
        if ($interruptBeforeWebhook) {
            Invoice::created(fn () => DB::afterCommit(static fn () => throw new RuntimeException('Post-commit listener interrupted')));
        } else {
            Queue::swap(new class($this->app) extends QueueFake
            {
                public function push($job, $data = '', $queue = null)
                {
                    if ($job instanceof DispatchWebhookJob) {
                        throw new RuntimeException('Queue unavailable');
                    }

                    return parent::push($job, $data, $queue);
                }
            });
        }

        $invoice = $this->createWithDocument();
        $call = $webhook->calls()->sole();
        $eventId = $call->payload['event_id'];
        $this->assertSame($invoice->id, $call->payload['invoice_id']);
        $this->assertSame('pending', $call->status);
        $this->assertNotNull($call->next_retry_at);
        $this->assertSame(self::FIRST, $this->get(route('invoices.justificatif.download', $invoice))->assertOk()->streamedContent());
        $this->assertSame(hash('sha256', self::FIRST), IssuedDocumentFile::query()->sole()->checksum_sha256);
        $this->assertSame(1, $this->usedSlots());
        $this->assertSame(strlen(self::FIRST), app(OrganizationDocumentStorageService::class)->bytesUsed($this->orgA));

        Queue::fake([DispatchWebhookJob::class]);
        if (! $interruptBeforeWebhook) {
            $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
            Queue::assertNothingPushed();
        }
        $this->travel(2)->minutes();
        $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
        $this->artisan('webhooks:dispatch-pending')->assertSuccessful();
        Queue::assertPushed(DispatchWebhookJob::class, 1);
        Http::fake(['*' => Http::response('', 204)]);
        Queue::pushed(DispatchWebhookJob::class)->sole()->withFakeQueueInteractions()->handle();
        $this->assertSame('success', $call->fresh()->status);
        $this->assertNull($call->fresh()->next_retry_at);
        Http::assertSent(fn ($request) => $request->hasHeader('X-Webhook-Id', $call->id)
            && $request->hasHeader('X-Webhook-Event-Id', $eventId));
    }

    private function limits(int $storage = -1): void
    {
        $quotas = $this->mock(OrganizationQuotaResolver::class);
        $quotas->shouldReceive('maxOrganizations', 'maxUsers', 'maxOcrScansPerDay', 'maxOcrScansPerMonth')->andReturn(-1);
        $quotas->shouldReceive('maxInvoicesPerMonth')->andReturn(1);
        $quotas->shouldReceive('maxStorageBytes')->andReturn($storage);
    }

    private function usedSlots(): int
    {
        return (int) Cache::get('invoices_monthly:'.$this->orgA->id.':'.now()->format('Y-m'));
    }

    private function edit(Invoice $invoice, ?string $bytes = null): TestResponse
    {
        return $this->post(route('invoices.update', $invoice),
            ['number' => $invoice->number, ...$this->form($bytes)],
            ['X-HTTP-Method-Override' => 'PUT'],
        );
    }

    private function createWithDocument(): Invoice
    {
        $response = $this->post(route('invoices.store'), $this->form(self::FIRST))->assertSessionHasNoErrors();
        $invoice = Invoice::query()->where('organization_id', $this->orgA->id)->sole();
        $response->assertRedirect(route('invoices.show', $invoice));
        $this->assertNotNull($invoice->justificatif_path);

        return $invoice;
    }

    /** @return array<string, mixed> */
    private function form(?string $bytes = null): array
    {
        return [
            'issue_date' => '2026-11-23', 'due_date' => '2026-12-23', 'currency' => 'CHF',
            'lines' => [['description' => 'Consulting', 'quantity' => 2, 'unit_price' => '17.50']],
            ...($bytes === null ? [] : ['justificatif' => UploadedFile::fake()->createWithContent('original.pdf', $bytes)]),
        ];
    }
}
