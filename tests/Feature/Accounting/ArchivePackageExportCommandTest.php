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

class ArchivePackageExportCommandTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_cli_exports_the_preserved_manifest_and_will_not_overwrite_an_existing_export(): void
    {
        $this->setUpOrganization();
        Storage::fake('local');
        $year = FiscalYear::factory()->for($this->organization)->create();
        $service = app(ArchivePackageService::class);
        $package = $service->request($this->organization, $year, $this->user);
        $service->prepare($package);
        $output = sys_get_temp_dir().'/gaeld-cli-export-'.bin2hex(random_bytes(12)).'.zip';
        try {
            $this->artisan('archive:package', ['operation' => 'export', 'package' => $package->id, '--output' => $output])->assertSuccessful();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($output));
            $this->assertSame($package->manifest_sha256, hash('sha256', $zip->getFromName('manifest.json')));
            $zip->close();
            $hash = hash_file('sha256', $output);
            $this->artisan('archive:package', ['operation' => 'export', 'package' => $package->id, '--output' => $output])->assertFailed();
            $this->assertSame($hash, hash_file('sha256', $output));
            $this->assertSame(1, ArchiveAccessEvent::query()->where('action', 'package.exported')->where('metadata->channel', 'cli')->count());
        } finally {
            @unlink($output);
        }
    }
}
