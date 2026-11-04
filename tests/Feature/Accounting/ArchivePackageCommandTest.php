<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Models\ArchiveAccessEvent;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchivePackageService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;
use ZipArchive;

class ArchivePackageCommandTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_cli_exports_a_complete_package_and_refuses_to_overwrite_it(): void
    {
        $this->setUpOrganization();
        Storage::fake('local');
        $year = FiscalYear::factory()->create(['organization_id' => $this->organization->id]);
        $package = app(ArchivePackageService::class)->request($this->organization, $year, $this->user);
        $this->artisan('archive:package', ['operation' => 'prepare', 'package' => $package->id])->assertSuccessful();
        $output = Storage::disk('local')->path('download.zip');

        $this->artisan('archive:package', ['operation' => 'export', 'package' => $package->id, '--output' => $output])->assertSuccessful();
        $zip = new ZipArchive;
        $this->assertTrue($zip->open($output));
        $this->assertSame($package->id, json_decode($zip->getFromName('manifest.json'), true)['package_id']);
        $this->assertNotFalse($zip->getFromName('data/snapshot.json'));
        $zip->close();
        $hash = hash_file('sha256', $output);

        $this->artisan('archive:package', ['operation' => 'export', 'package' => $package->id, '--output' => $output])->assertFailed();
        $this->assertSame($hash, hash_file('sha256', $output));
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'package.exported')->count());
        $this->artisan('archive:package', ['operation' => 'inspect', 'package' => $package->id])->assertSuccessful();
        $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'package.inspected')->count());
    }
}
