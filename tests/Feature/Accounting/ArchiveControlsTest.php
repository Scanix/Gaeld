<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\ProofPipeline;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Accounting\Services\ArchiveProofService;
use App\Domains\Accounting\Services\IssuedDocumentPreservation;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchiveControlsTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_issued_documents_keep_every_version_and_detect_a_lost_original(): void
    {
        $invoice = Invoice::factory()->for($this->organization)->create();
        $service = app(IssuedDocumentPreservation::class);
        $first = $service->store($invoice, 'invoice_pdf', '%PDF-1.7 original', '2026-07-08');
        $second = $service->store($invoice, 'invoice_pdf', '%PDF-1.7 replacement', '2026-07-08');
        $same = $service->store($invoice, 'invoice_pdf', '%PDF-1.7 original', '2026-07-08');
        $this->assertSame($first->id, $same->id);
        $this->assertSame(2, $second->version);
        $this->assertSame('%PDF-1.7 original', Storage::disk('local')->get($first->storage_path));
        Storage::disk('local')->delete($first->storage_path);
        $this->expectException(\RuntimeException::class);
        $service->store($invoice, 'invoice_pdf', '%PDF-1.7 original', '2026-07-08');
    }

    public function test_retention_cannot_be_shortened_and_a_hold_is_audited(): void
    {
        $package = $this->package();
        $until = $package->retention_until->toIso8601String();
        $this->artisan('archive:retain', ['package' => $package->id, '--until' => '2028-01-01', '--reason' => 'Invalid shortening request'])->assertFailed();
        $this->assertSame($until, $package->refresh()->retention_until->toIso8601String());
        $this->artisan('archive:retain', ['package' => $package->id, '--hold' => true, '--reason' => 'Pending litigation review'])->assertSuccessful();
        $this->assertTrue($package->refresh()->legal_hold);
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'package.retention_changed')->count());
    }

    public function test_annual_control_cannot_claim_success_without_independent_recovery(): void
    {
        $this->artisan('archive:check', ['--organization' => $this->organization->id, '--annual' => true])->assertFailed();
        $report = ArchiveAccessEvent::query()->where('action', 'control.annual')->firstOrFail();
        $this->assertFalse($report->metadata['valid']);
        $this->assertNotEmpty($report->metadata['issues']);
    }

    public function test_daily_control_records_tampering_as_an_incident(): void
    {
        $package = $this->package();
        $service = app(ArchivePackageService::class);
        $service->prepare($package);
        Storage::disk('local')->put($package->package_path.'/data/snapshot.json', 'altered');
        $this->artisan('archive:check', ['--organization' => $this->organization->id])->assertFailed();
        $this->assertSame('incident', $package->refresh()->state);
        $report = ArchiveAccessEvent::query()->where('action', 'control.daily')->firstOrFail();
        $this->assertFalse($report->metadata['valid']);
    }

    public function test_sealing_refreshes_retention_and_hold_after_acquiring_the_package_lock(): void
    {
        $package = $this->package(true);
        Storage::disk('local')->put($package->package_path.'/manifest.json', '{}');
        $package->update(['manifest_sha256' => hash('sha256', '{}'), 'state' => 'prepared']);
        $stale = ArchivePackage::query()->findOrFail($package->id);
        $latest = ArchivePackage::query()->findOrFail($package->id);
        $extended = $latest->retention_until->copy()->addYears(2);
        $latest->update(['retention_until' => $extended, 'legal_hold' => true]);
        $receipt = ['objects' => ['new-proof']];
        $this->mock(ProofPipeline::class)->shouldReceive('process')->once()
            ->withArgs(fn (string $directory, string $until, callable $stage): bool => $directory === Storage::disk('local')->path($package->package_path)
                && $until === $extended->toIso8601String())
            ->andReturn(['state' => 'verified', 'storage' => $receipt, 'next_renewal_at' => now()->addDays(300)->toIso8601String()]);
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('protect')->once()
            ->with($receipt, $extended->toIso8601String(), true)
            ->andReturn(['objects' => ['new-proof'], 'legal_hold' => true]);

        $result = app(ArchiveProofService::class)->seal($stale);

        $this->assertSame('verified', $result->state);
        $this->assertTrue($result->legal_hold);
        $this->assertTrue($result->proof_result['storage']['legal_hold']);
        $this->assertSame($extended->toIso8601String(), $result->retention_until->toIso8601String());
    }

    private function package(bool $archivalPdf = false): ArchivePackage
    {
        $year = FiscalYear::factory()->create(['organization_id' => $this->organization->id, 'start_date' => '2026-07-08', 'end_date' => '2027-12-31']);

        return app(ArchivePackageService::class)->request($this->organization, $year, $this->user, archivalPdf: $archivalPdf);
    }
}
