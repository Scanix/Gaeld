<?php

namespace Tests\Feature\Invoicing;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Invoicing\Actions\SyncInvoiceLinesAction;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
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
