<?php

namespace Tests\Feature\Migration;

use App\Domains\Migration\Enums\ImportStatus;
use App\Domains\Migration\Enums\Platform;
use App\Domains\Migration\Models\MigrationSession;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class MigrationControllerTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    // ────────────────────────────────────────────────
    // Authorization
    // ────────────────────────────────────────────────

    public function test_unauthenticated_user_is_redirected(): void
    {
        $this->get('/migration')->assertRedirect('/login');
    }

    public function test_index_returns_migration_page(): void
    {
        $response = $this->actAsOrg()->get('/migration');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Migration/Index')
            ->has('sessions')
            ->has('platforms')
        );
    }

    // ────────────────────────────────────────────────
    // Store (create session)
    // ────────────────────────────────────────────────

    public function test_store_creates_migration_session(): void
    {
        $response = $this->actAsOrg()->post('/migration', [
            'platform' => 'bexio',
        ]);

        $response->assertRedirect();

        $this->assertDatabaseHas('migration_sessions', [
            'organization_id' => $this->organization->id,
            'platform' => 'bexio',
            'status' => ImportStatus::Pending->value,
        ]);
    }

    public function test_store_validates_platform(): void
    {
        $response = $this->actAsOrg()->post('/migration', [
            'platform' => 'nonexistent',
        ]);

        $response->assertSessionHasErrors('platform');
    }

    // ────────────────────────────────────────────────
    // Show
    // ────────────────────────────────────────────────

    public function test_show_displays_session(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->get("/migration/{$session->id}");

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Migration/Show')
            ->has('session')
            ->has('supportedDataTypes')
            ->has('acceptedExtensions')
        );
    }

    public function test_show_denies_access_to_other_org_session(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'EUR']);
        $session = MigrationSession::create([
            'organization_id' => $otherOrg->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->get("/migration/{$session->id}");

        $response->assertStatus(404);
    }

    // ────────────────────────────────────────────────
    // Destroy
    // ────────────────────────────────────────────────

    public function test_destroy_deletes_session(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->delete("/migration/{$session->id}");

        $response->assertRedirect('/migration');
        $this->assertDatabaseMissing('migration_sessions', ['id' => $session->id]);
    }

    public function test_destroy_denies_access_to_other_org_session(): void
    {
        $otherOrg = Organization::create(['name' => 'Other Org', 'currency' => 'EUR']);
        $session = MigrationSession::create([
            'organization_id' => $otherOrg->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->delete("/migration/{$session->id}");

        $response->assertStatus(404);
    }

    // ────────────────────────────────────────────────
    // Upload
    // ────────────────────────────────────────────────

    public function test_upload_validates_file_required(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::GenericCsv,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->post("/migration/{$session->id}/upload", [
            'data_type' => 'accounts',
        ]);

        $response->assertSessionHasErrors('file');
    }

    // ────────────────────────────────────────────────
    // Execute
    // ────────────────────────────────────────────────

    public function test_execute_validates_data_types_required(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $response = $this->actAsOrg()->post("/migration/{$session->id}/execute", []);

        $response->assertSessionHasErrors('data_types');
    }

    // ────────────────────────────────────────────────
    // Session model
    // ────────────────────────────────────────────────

    public function test_session_model_update_data_type_status(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $session->updateDataTypeStatus('accounts', ImportStatus::Completed);
        $session->updateDataTypeStatus('contacts', ImportStatus::Failed);

        $session->refresh();
        $this->assertSame('completed', $session->data_types_status['accounts']);
        $this->assertSame('failed', $session->data_types_status['contacts']);
    }

    public function test_session_model_increment_imported_count(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $session->incrementImportedCount('accounts', 10);
        $session->incrementImportedCount('accounts', 5);

        $session->refresh();
        $this->assertSame(15, $session->imported_counts['accounts']);
    }

    public function test_session_model_mark_completed_with_all_success(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Importing,
            'data_types_status' => [
                'accounts' => ImportStatus::Completed->value,
                'contacts' => ImportStatus::Completed->value,
            ],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $session->markCompleted();
        $session->refresh();

        $this->assertSame(ImportStatus::Completed, $session->status);
        $this->assertNotNull($session->completed_at);
    }

    public function test_session_model_mark_completed_with_mixed_results(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Importing,
            'data_types_status' => [
                'accounts' => ImportStatus::Completed->value,
                'contacts' => ImportStatus::Failed->value,
            ],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $session->markCompleted();
        $session->refresh();

        $this->assertSame(ImportStatus::PartiallyCompleted, $session->status);
    }

    public function test_session_model_add_errors(): void
    {
        $session = MigrationSession::create([
            'organization_id' => $this->organization->id,
            'platform' => Platform::Bexio,
            'status' => ImportStatus::Pending,
            'data_types_status' => [],
            'imported_counts' => [],
            'errors' => [],
            'created_by' => $this->user->id,
        ]);

        $session->addErrors('accounts', ['Error 1', 'Error 2']);
        $session->addErrors('accounts', ['Error 3']);

        $session->refresh();
        $this->assertCount(3, $session->errors['accounts']);
    }
}
