<?php

namespace App\Providers;

use App\Domains\Accounting\Contracts\ArchivePackageBuilder;
use App\Domains\Accounting\Proof\Contracts\ArchiveProofVerifier;
use App\Domains\Accounting\Proof\Contracts\ManifestSigner;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\Contracts\TimestampAuthority;
use App\Domains\Accounting\Proof\OpenSslManifestSigner;
use App\Domains\Accounting\Proof\Rfc3161TimestampAuthority;
use App\Domains\Accounting\Proof\S3ComplianceStorage;
use App\Domains\Accounting\Proof\StandaloneArchiveVerifier;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Jobs\ProcessArchivePackage;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ServiceProvider;

class ArchiveServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        config(['queue.connections.archives' => [
            ...config('queue.connections.redis', []), 'retry_after' => 1860, 'after_commit' => true,
        ]]);
        $this->app->bind(ArchivePackageBuilder::class, ArchivePackageService::class);
        $this->app->bind(ManifestSigner::class, OpenSslManifestSigner::class);
        $this->app->bind(TimestampAuthority::class, Rfc3161TimestampAuthority::class);
        $this->app->bind(ProtectedArchiveStorage::class, S3ComplianceStorage::class);
        $this->app->bind(ArchiveProofVerifier::class, StandaloneArchiveVerifier::class);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        Queue::route([
            ProcessArchivePackage::class => [
                config('queue.default') === 'sync' ? 'sync' : 'archives', 'archives',
            ],
        ]);
    }
}
