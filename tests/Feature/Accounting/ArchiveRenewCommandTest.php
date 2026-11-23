<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\RenewArchiveEvidence;
use App\Domains\Accounting\Services\ArchivePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchiveRenewCommandTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        Storage::fake('local');
    }

    public function test_due_renewal_preserves_existing_proofs_and_protects_new_evidence_under_hold(): void
    {
        $package = $this->package();
        $package->update(['legal_hold' => true]);
        $next = now()->addDays(300)->toIso8601String();
        $result = ['verification' => ['valid' => true], 'storage' => ['objects' => ['new']], 'next_renewal_at' => $next];
        $this->mock(RenewArchiveEvidence::class)->shouldReceive('renew')->once()->andReturn($result);
        $this->mock(ProtectedArchiveStorage::class)->shouldReceive('protect')->once()->with($result['storage'], $package->retention_until->toIso8601String(), true)->andReturn(['objects' => ['new'], 'legal_hold' => true]);
        $this->artisan('archive:renew', ['--due' => true])->assertSuccessful();
        $package->refresh();
        $this->assertSame('verified', $package->state);
        $this->assertSame('historical-signature', $package->proof_result['signature']);
        $this->assertTrue($package->proof_result['storage']['legal_hold']);
        $this->assertArrayNotHasKey('renewal_pending', $package->proof_result);
        $this->assertCount(1, $package->proof_result['renewals']);
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'proof.renewed')->count());
    }

    public function test_failed_renewal_retains_a_resumable_identifier_and_old_storage_receipt(): void
    {
        $package = $this->package();
        $id = '20261104T120000Z-aabbccdd';
        $this->mock(RenewArchiveEvidence::class)->shouldReceive('renew')->once()->with(Mockery::type('string'), $id, Mockery::type('string'), $package->manifest_sha256)->andThrow(new RuntimeException('Provider unavailable'));
        $this->artisan('archive:renew', ['package' => $package->id, '--renewal-id' => $id])->assertFailed();
        $package->refresh();
        $this->assertSame('incident', $package->state);
        $this->assertSame($id, $package->proof_result['renewal_pending']);
        $this->assertSame(['objects' => ['old']], $package->proof_result['storage']);
        $this->assertTrue($package->check_due_at->isPast());
    }

    private function package(): ArchivePackage
    {
        $year = FiscalYear::factory()->for($this->organization)->create(['start_date' => '2026-07-08', 'end_date' => '2027-12-31']);
        $package = app(ArchivePackageService::class)->request($this->organization, $year, $this->user, archivalPdf: true);
        $package->update(['state' => 'verified', 'manifest_sha256' => str_repeat('a', 64), 'proof_result' => ['storage' => ['objects' => ['old']], 'signature' => 'historical-signature'], 'check_due_at' => now()->subDay()]);

        return $package;
    }
}
