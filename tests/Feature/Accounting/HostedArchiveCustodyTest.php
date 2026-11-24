<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\ProofPipeline;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use App\Jobs\ProcessArchivePackage;
use App\Support\Contracts\ArchiveCustodyEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class HostedArchiveCustodyTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->withoutVite();
        config(['features.saas' => true, 'archiving.proofs_enabled' => true]);
        Storage::fake('local');
    }

    public function test_manual_requests_need_explicit_enrollment_but_content_capture_remains_available(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create();
        $this->actAsOrg()->get('/accounting/conservation')->assertOk()
            ->assertInertia(fn ($page) => $page->where('canRequestProof', false));
        $this->post('/accounting/conservation', ['fiscal_year_id' => $year->id, 'archival_pdf' => true, 'custody_enrollment_id' => (string) Str::uuid()])->assertForbidden();
        $this->artisan('archive:capture', ['--organization' => $this->organization->id, '--archival-pdf' => true])->assertFailed();
        $this->assertDatabaseCount('archive_packages', 0);
        $this->post('/accounting/conservation', ['fiscal_year_id' => $year->id])->assertRedirect();
        $this->assertDatabaseHas('archive_packages', ['organization_id' => $this->organization->id, 'archival_pdf' => false]);

        $this->mock(ArchiveCustodyEnrollment::class)->shouldReceive('activeId')->with($this->organization->id)->andReturn((string) Str::uuid());
        $this->post('/accounting/conservation', ['fiscal_year_id' => $year->id, 'archival_pdf' => true])->assertRedirect();
        $this->assertDatabaseHas('archive_packages', ['organization_id' => $this->organization->id, 'archival_pdf' => true]);
    }

    public function test_ending_admission_changes_new_captures_and_preserves_the_queued_obligation(): void
    {
        $other = Organization::factory()->create();
        foreach ([$this->organization, $other] as $org) {
            FiscalYear::factory()->for($org)->create(['start_date' => now()->startOfYear(), 'end_date' => now()->endOfYear()]);
        }
        $grant = (string) Str::uuid();
        $registry = $this->mock(ArchiveCustodyEnrollment::class);
        $registry->shouldReceive('activeId')->with($this->organization->id)->andReturn($grant, null);
        $registry->shouldReceive('activeId')->with($other->id)->andReturn(null);
        $this->artisan('archive:capture')->assertSuccessful();
        $admitted = ArchivePackage::withoutGlobalScopes()->where('organization_id', $this->organization->id)->sole();
        $this->assertSame($grant, $admitted->snapshot['custody_enrollment'][0]['id']);
        $this->assertDatabaseHas('archive_packages', ['organization_id' => $other->id, 'archival_pdf' => false]);

        $this->artisan('archive:capture', ['--organization' => $this->organization->id])->assertSuccessful();
        $this->assertDatabaseHas('archive_packages', ['organization_id' => $this->organization->id, 'archival_pdf' => false]);
        $this->fakePdfConversion();
        $this->mock(ProofPipeline::class)->shouldReceive('process')->once()->andReturn(['storage' => ['synthetic' => true]]);
        (new ProcessArchivePackage($admitted->id))->handle(app(ArchivePackageService::class));
        $this->assertSame('verified', $admitted->refresh()->state);
        $this->assertNotNull($admitted->fresh()->verified_at);
    }

    public function test_legacy_unadmitted_capture_cannot_begin_hosted_deposit_on_resume(): void
    {
        $year = FiscalYear::factory()->for($this->organization)->create();
        config(['features.saas' => false]);
        $service = app(ArchivePackageService::class);
        $legacy = $service->request($this->organization, $year, archivalPdf: true);
        $this->assertArrayNotHasKey('custody_enrollment', $legacy->snapshot);
        config(['features.saas' => true]);

        $this->fakePdfConversion();
        $this->mock(ProofPipeline::class)->shouldNotReceive('process');
        $this->artisan('archive:package', ['operation' => 'resume', 'package' => $legacy->id])->assertSuccessful();
        $this->artisan('archive:package', ['operation' => 'prepare', 'package' => $legacy->id])->assertFailed();
        $this->assertSame('incident', $legacy->refresh()->state);
        $this->assertNull($legacy->proof_result);
    }

    private function fakePdfConversion(): void
    {
        $this->mock(PdfArchivalConverter::class)->shouldReceive('convert')->andReturnUsing(function ($source, $destination) {
            copy($source, $destination);

            return ['validation_report' => 'synthetic PDF response'];
        });
    }
}
