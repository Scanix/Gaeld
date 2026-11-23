<?php

namespace App\Jobs;

use App\Domains\Accounting\Models\ArchiveOutbox;
use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchivePackageService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class ProcessArchivePackage implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 1800;

    public int $uniqueFor = 2100;

    public function __construct(public readonly string $packageId)
    {
        $this->onQueue('archives');
    }

    public function uniqueId(): string
    {
        return $this->packageId;
    }

    /** @return list<int> */
    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(ArchivePackageService $packages): void
    {
        $package = ArchivePackage::withoutGlobalScopes()->findOrFail($this->packageId);
        $package = $packages->prepare($package, $package->archival_pdf);
        ArchiveOutbox::query()->where('archive_package_id', $package->id)->update(['completed_at' => now()]);
    }

    public function failed(?Throwable $exception): void
    {
        $package = ArchivePackage::withoutGlobalScopes()->find($this->packageId);
        if ($package === null) {
            return;
        }
        $package->update(['state' => $package->manifest_sha256 === null ? 'failed' : 'incident', 'failure' => $exception?->getMessage() ?? 'Archive job failed.']);
        ArchiveOutbox::query()->where('archive_package_id', $package->id)->update(['available_at' => now()->addHour()]);
        app(ArchiveAccessLog::class)->record($package->organization_id, $package->id, 'package.failed', metadata: ['stage' => $package->manifest_sha256 === null ? 'prepare' : 'integrity']);
    }
}
