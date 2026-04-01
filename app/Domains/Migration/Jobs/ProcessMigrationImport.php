<?php

namespace App\Domains\Migration\Jobs;

use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class ProcessMigrationImport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public int $timeout = 600;

    /**
     * @param  array<string, Collection>  $rowsByType
     */
    public function __construct(
        private readonly MigrationSession $session,
        private readonly array $rowsByType,
    ) {}

    public function handle(MigrationOrchestrator $orchestrator): void
    {
        $organization = Organization::findOrFail($this->session->organization_id);

        $orchestrator->executeAll(
            $this->session,
            $this->rowsByType,
            $organization,
        );
    }
}
