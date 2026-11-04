<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use App\Jobs\ProcessArchivePackage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchiveReleaseBoundaryTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_three_company_historical_backfill_resumes_with_separate_complete_packages(): void
    {
        $companies = [$this->organization, Organization::factory()->create(), Organization::factory()->create()];
        foreach ($companies as $index => $company) {
            FiscalYear::factory()->for($company)->create(['start_date' => '2026-07-08', 'end_date' => '2027-12-31']);
            Account::create(['organization_id' => $company->id, 'code' => '1020', 'name' => 'Company '.($index + 1).' bank', 'type' => AccountType::Asset]);
        }
        $this->artisan('archive:backfill')->assertSuccessful();
        $packages = ArchivePackage::withoutGlobalScopes()->get();
        $this->assertCount(3, $packages);
        $service = app(ArchivePackageService::class);
        $first = $packages->first();
        (new ProcessArchivePackage($first->id))->handle($service);
        $firstHash = $first->refresh()->manifest_sha256;

        $this->artisan('archive:backfill')->assertSuccessful();
        $this->assertSame(3, ArchivePackage::withoutGlobalScopes()->count());
        foreach ($packages as $package) {
            (new ProcessArchivePackage($package->id))->handle($service);
            $package->refresh();
            $this->assertTrue($service->verify($package)['valid']);
            $this->assertSame('prepared', $package->state);
            $this->assertSame('historical_backfill', $package->provenance);
            $this->assertSame($package->organization_id, $package->snapshot['organization'][0]['id']);
            $this->assertCount(1, $package->snapshot['accounts']);
            $this->assertSame($package->organization_id, $package->snapshot['accounts'][0]['organization_id']);
            $manifest = json_decode(Storage::disk('local')->get($package->package_path.'/manifest.json'), true, 512, JSON_THROW_ON_ERROR);
            $this->assertFalse($manifest['historical_integrity_asserted']);
            $this->assertSame('gaeld-content-package/1.0.0', $manifest['proof_profile']);
            $this->assertSame('PDF', $manifest['report_profile']);
            $this->assertSame('2026-07-08', $manifest['fiscal_year']['start_date']);
            $this->assertSame('2027-12-31', $manifest['fiscal_year']['end_date']);
        }
        $this->assertSame($firstHash, $first->refresh()->manifest_sha256);
        $this->assertSame(3, ArchiveOutbox::query()->whereNotNull('completed_at')->count());
    }

    public function test_unexpected_files_are_an_integrity_incident_and_cannot_be_exported(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create();
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $year);
        $service->prepare($package);
        Storage::disk('local')->put($package->package_path.'/unexpected.txt', 'Unrecorded content');

        $this->assertFalse($service->verify($package)['valid']);
        $this->assertSame('incident', $package->refresh()->state);
        $this->expectException(\RuntimeException::class);
        $service->export($package);
    }

    public function test_web_and_cli_capture_sealing_requests_without_silently_downgrading_them(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->actAsOrg()->post('/accounting/conservation', ['fiscal_year_id' => $year->id, 'archival_pdf' => true])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->artisan('archive:capture', ['--fiscal-year' => $year->id, '--archival-pdf' => true])->assertSuccessful();
        $this->assertGreaterThan(0, ArchivePackage::withoutGlobalScopes()->count());
        $this->assertSame(0, ArchivePackage::withoutGlobalScopes()->where('archival_pdf', false)->count());
        $this->assertSame(ArchivePackage::withoutGlobalScopes()->count(), ArchiveOutbox::query()->count());
    }

    public function test_r3_adds_proof_stages_without_enabling_unconfigured_providers(): void
    {
        foreach (['sealed_at', 'deposited_at', 'check_due_at', 'proof_result'] as $column) {
            $this->assertTrue(Schema::hasColumn('archive_packages', $column));
        }
        $this->assertDirectoryExists(app_path('Domains/Accounting/Proof'));
        $this->assertFileExists(app_path('Console/Commands/ArchiveRenewCommand.php'));
        $this->assertFalse(config('archiving.proofs_enabled'));
    }

    public function test_daily_control_detects_an_uncaptured_financial_year(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $this->artisan('archive:check', ['--organization' => $this->organization->id])->assertFailed();
        $report = ArchiveAccessEvent::query()->where('action', 'control.daily')->firstOrFail();
        $this->assertFalse($report->metadata['valid']);
        $this->assertContains('Financial year has no preservation package: '.$year->id, $report->metadata['issues']);
    }

    public function test_annual_control_retains_only_the_current_company_recovery_inventory(): void
    {
        $service = app(ArchivePackageService::class);
        $packages = [];
        foreach ([$this->organization, Organization::factory()->create()] as $company) {
            $year = FiscalYear::factory()->for($company)->create();
            $package = $service->request($company, $year);
            $service->prepare($package);
            $packages[$package->id] = $package->manifest_sha256;
        }
        $path = sys_get_temp_dir().'/r2-recovery-'.bin2hex(random_bytes(12)).'.json';
        file_put_contents($path, json_encode([
            'environment' => 'independent-restoration-environment', 'operator' => 'Recovery operator',
            'completed_at' => now()->subMinute()->toIso8601String(), 'packages' => $packages,
            'extra_sensitive_data' => 'Must not be copied into company controls',
        ], JSON_THROW_ON_ERROR));
        try {
            $this->artisan('archive:check', ['--annual' => true, '--recovery-report' => $path])->assertSuccessful();
            foreach (ArchiveAccessEvent::withoutGlobalScopes()->where('action', 'control.annual')->get() as $event) {
                $own = ArchivePackage::withoutGlobalScopes()->where('organization_id', $event->organization_id)->firstOrFail();
                $this->assertSame([$own->id => $own->manifest_sha256], $event->metadata['recovery_attestation']['packages']);
                $this->assertArrayNotHasKey('extra_sensitive_data', $event->metadata['recovery_attestation']);
                $this->assertFalse($event->metadata['proofs_checked']);
            }
        } finally {
            unlink($path);
        }
    }
}
