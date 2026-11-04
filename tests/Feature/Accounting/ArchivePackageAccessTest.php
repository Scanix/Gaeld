<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\LegalArchive;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchivePackageAccessTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_three_companies_have_separate_packages_and_snapshots_are_not_exposed(): void
    {
        $own = $this->package($this->organization);
        $this->package(Organization::factory()->create());
        $this->package(Organization::factory()->create());

        $this->actAsOrg()->get('/accounting/conservation')->assertOk()->assertInertia(fn ($page) => $page
            ->component('Accounting/Archives/Packages')->has('packages.data', 1)
            ->where('packages.data.0.id', $own->id)->missing('packages.data.0.snapshot')
            ->where('canManage', true));
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'packages.viewed')->count());
    }

    public function test_foreign_package_cannot_be_exported_verified_or_retried(): void
    {
        $foreign = $this->package(Organization::factory()->create());
        $this->actAsOrg()->get('/accounting/conservation/'.$foreign->id.'/export')->assertNotFound();
        $this->actAsOrg()->post('/accounting/conservation/'.$foreign->id.'/verify')->assertNotFound();
        $this->actAsOrg()->post('/accounting/conservation/'.$foreign->id.'/resume')->assertNotFound();
    }

    public function test_viewer_can_read_but_cannot_prepare_or_retry(): void
    {
        $package = $this->package($this->organization);
        $viewer = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->organization->users()->attach($viewer->id, ['role' => 'viewer']);
        $this->assignOrganizationRole($viewer, $this->organization, 'viewer');
        $this->actingAs($viewer)->withSession(['current_organization_id' => $this->organization->id]);
        $this->get('/accounting/conservation')->assertOk()->assertInertia(fn ($page) => $page->where('canManage', false));
        $this->post('/accounting/conservation', ['fiscal_year_id' => $package->fiscal_year_id])->assertForbidden();
        $this->post('/accounting/conservation/'.$package->id.'/resume')->assertForbidden();
    }

    public function test_foreign_fiscal_year_cannot_be_captured(): void
    {
        $foreign = $this->package(Organization::factory()->create());
        $this->actAsOrg()->post('/accounting/conservation', ['fiscal_year_id' => $foreign->fiscal_year_id])
            ->assertSessionHasErrors('fiscal_year_id');
    }

    public function test_preservation_is_available_without_an_active_saas_subscription(): void
    {
        config(['features.saas' => true]);
        $this->actAsOrg()->get('/accounting/conservation')->assertOk();
        $this->actAsOrg()->get('/accounting/archives')->assertOk();
    }

    public function test_unsubscribed_member_can_switch_companies_for_preservation_but_not_open_paid_pages(): void
    {
        config(['features.saas' => true]);
        Route::get('/qa-billing', fn () => 'billing')->name('billing.index');
        Route::getRoutes()->refreshNameLookups();
        $second = Organization::factory()->create();
        $second->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $second, 'owner');
        $own = $this->package($second);
        $foreign = $this->package(Organization::factory()->create());
        $this->actAsOrg()->post('/organizations/'.$second->id.'/switch')
            ->assertRedirect('/dashboard')->assertSessionHas('current_organization_id', $second->id);
        $this->get('/accounting/conservation')->assertOk()->assertInertia(fn ($page) => $page
            ->has('packages.data', 1)->where('packages.data.0.id', $own->id));
        $this->get('/dashboard')->assertRedirect('/qa-billing');
        $this->post('/organizations/'.$foreign->organization_id.'/switch')->assertNotFound();
        $this->get('/accounting/conservation/'.$foreign->id.'/export')->assertNotFound();
        $this->post('/accounting/conservation/'.$foreign->id.'/verify')->assertNotFound();
        $this->post('/accounting/conservation/'.$foreign->id.'/resume')->assertNotFound();
    }

    public function test_web_verification_reports_a_content_integrity_failure(): void
    {
        $package = $this->package($this->organization);
        $this->mock(ArchivePackageService::class)->shouldReceive('verify')->once()
            ->withArgs(fn (ArchivePackage $candidate) => $candidate->id === $package->id)
            ->andReturn(['valid' => false, 'issues' => ['Unexpected package content.']]);

        $this->actAsOrg()->post('/accounting/conservation/'.$package->id.'/verify')
            ->assertRedirect()->assertSessionHas('error');
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'package.integrity_checked')->count());
    }

    public function test_a_missing_historical_pdf_is_not_silently_replaced(): void
    {
        $year = FiscalYear::factory()->create(['organization_id' => $this->organization->id, 'start_date' => '2026-01-01', 'end_date' => '2026-12-31']);
        $archive = LegalArchive::create([
            'organization_id' => $this->organization->id, 'fiscal_year_id' => $year->id,
            'document_type' => 'pdf_journal', 'document_id' => 'pdf-'.$year->id, 'fiscal_year' => 2026,
            'checksum_sha256' => str_repeat('a', 64), 'storage_path' => 'lost.pdf', 'archived_at' => now(), 'expires_at' => now()->addYears(10),
        ]);
        $this->actAsOrg()->get('/accounting/archives/year/2026/pdf/journal?fiscal_year_id='.$year->id)->assertStatus(409);
        $this->assertSame('lost.pdf', $archive->refresh()->storage_path);
        $this->assertSame(1, LegalArchive::query()->count());
    }

    private function package(Organization $organization): ArchivePackage
    {
        $year = FiscalYear::factory()->create(['organization_id' => $organization->id]);

        return ArchivePackage::create([
            'organization_id' => $organization->id, 'fiscal_year_id' => $year->id,
            'snapshot' => ['private' => 'must not be in page props'], 'snapshot_sha256' => str_repeat('a', 64),
            'package_path' => 'archives/packages/'.$organization->id.'/'.Str::uuid(),
            'captured_at' => now(), 'retention_until' => $year->end_date->copy()->addYears(10)->endOfDay(),
        ]);
    }
}
