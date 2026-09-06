<?php

namespace App\Domains\Migration\Jobs;

use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class ProcessMigrationImport implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 600;

    public int $uniqueFor = 7200;

    /**
     * @param  array<string, Collection<int, mixed>>  $rowsByType
     */
    public function __construct(
        private readonly MigrationSession $session,
        private readonly array $rowsByType,
    ) {}

    public function handle(MigrationOrchestrator $orchestrator): void
    {
        $session = MigrationSession::query()
            ->whereKey($this->session->getKey())
            ->firstOrFail();

        if (in_array($session->status, [ImportStatus::Completed, ImportStatus::Reversed], true)) {
            return;
        }

        $organization = Organization::findOrFail($session->organization_id);

        $orchestrator->executeAll(
            $session,
            $this->rowsByType,
            $organization,
        );
    }

    public function uniqueId(): string
    {
        return 'migration-session:'.$this->session->getKey();
    }

    public function failed(\Throwable $e): void
    {
        Log::error('Migration import job failed', [
            'session_id' => $this->session->id,
            'error' => $e->getMessage(),
        ]);

        $this->session->update([
            'status' => ImportStatus::Failed,
            'completed_at' => now(),
        ]);
    }
}
