<?php

namespace App\Providers;

use App\Domains\Accounting\Contracts\ArchivePackageBuilder;
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
