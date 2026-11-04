<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchiveInventoryCompletenessTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['archiving.s3.bucket' => 'preserved-archives', 'archiving.proofs_enabled' => false]);
    }

    public function test_missing_entire_company_is_detected_from_independent_catalog_even_when_profile_is_disabled(): void
    {
        $this->setUpOrganization();
        $missingCompany = (string) Str::uuid();
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('inventory')->once()->withNoArgs()->andReturn([
            ['organization_id' => $missingCompany, 'package_id' => (string) Str::uuid(), 'manifest_sha256' => str_repeat('a', 64)],
        ]);
        $this->artisan('archive:check')->assertFailed();
        $reports = Storage::disk('local')->files('preservation-controls/global');
        $this->assertCount(1, $reports);
        $report = json_decode(Storage::disk('local')->get($reports[0]), true);
        $this->assertFalse($report['valid']);
        $this->assertSame([$missingCompany], $report['protected_organizations']);
        $this->assertStringContainsString($missingCompany, $report['issues'][0]);
    }

    public function test_empty_local_database_cannot_claim_success_while_remote_companies_exist(): void
    {
        $missingCompany = (string) Str::uuid();
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('inventory')->once()->withNoArgs()->andReturn([
            ['organization_id' => $missingCompany, 'package_id' => (string) Str::uuid(), 'manifest_sha256' => str_repeat('b', 64)],
        ]);
        $this->artisan('archive:check')->assertFailed();
        $reports = Storage::disk('local')->files('preservation-controls/global');
        $report = json_decode(Storage::disk('local')->get($reports[0]), true);
        $this->assertFalse($report['valid']);
        $this->assertSame([], $report['local_organizations']);
    }

    public function test_missing_all_packages_is_detected_when_proof_profile_has_been_disabled(): void
    {
        $this->setUpOrganization();
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('inventory')->once()->withNoArgs()->andReturn([
            ['organization_id' => $this->organization->id, 'package_id' => (string) Str::uuid(), 'manifest_sha256' => str_repeat('c', 64)],
        ]);
        $this->artisan('archive:check')->assertFailed();
    }

    public function test_soft_deleted_companies_remain_in_the_integrity_control_scope(): void
    {
        $this->setUpOrganization();
        $this->organization->delete();
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('inventory')->once()->withNoArgs()->andReturn([]);
        $this->artisan('archive:check')->assertSuccessful();
        $reports = Storage::disk('local')->files('preservation-controls/global');
        $report = json_decode(Storage::disk('local')->get($reports[0]), true);
        $this->assertSame([$this->organization->id], $report['local_organizations']);
    }
}
