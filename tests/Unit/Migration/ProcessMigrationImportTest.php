<?php

namespace Tests\Unit\Migration;

use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Jobs\ProcessMigrationImport;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Migration\Services\MigrationOrchestrator;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessMigrationImportTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->organization = Organization::factory()->create([
            'name' => 'Migration Job Test Org',
            'currency' => 'CHF',
        ]);
    }

    public function test_job_is_unique_per_migration_session(): void
    {
        $session = $this->createSession();
        $job = new ProcessMigrationImport($session, []);

        $this->assertSame('migration-session:'.$session->id, $job->uniqueId());
        $this->assertSame(7200, $job->uniqueFor);
    }

    public function test_completed_session_is_not_reprocessed(): void
    {
        $session = $this->createSession(ImportStatus::Completed);
        $orchestrator = $this->createMock(MigrationOrchestrator::class);
        $orchestrator->expects($this->never())->method('executeAll');

        (new ProcessMigrationImport($session, []))->handle($orchestrator);
    }

    private function createSession(ImportStatus $status = ImportStatus::Pending): MigrationSession
    {
        return MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::GenericCsv,
            'status' => $status,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);
    }
}
