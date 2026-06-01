<?php

namespace Tests\Feature\Console;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CleanupSpamOrganizationsCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_dry_run_lists_but_does_not_delete(): void
    {
        $user = User::factory()->create();
        $bot = Organization::create(['name' => 'lsVXrbcAjoMkQSLdDhj', 'currency' => 'CHF']);
        $bot->users()->attach($user->id, ['role' => 'owner']);

        $this->artisan('gaeld:cleanup-spam-orgs')
            ->expectsOutputToContain('Dry-run')
            ->assertSuccessful();

        $this->assertDatabaseHas('organizations', ['id' => $bot->id, 'deleted_at' => null]);
    }

    public function test_force_deletes_matching_orgs(): void
    {
        $user = User::factory()->create();
        $bot = Organization::create(['name' => 'lsVXrbcAjoMkQSLdDhj', 'currency' => 'CHF']);
        $bot->users()->attach($user->id, ['role' => 'owner']);

        $this->artisan('gaeld:cleanup-spam-orgs', ['--force' => true])
            ->assertSuccessful();

        $this->assertSoftDeleted('organizations', ['id' => $bot->id]);
    }

    public function test_legitimate_org_name_is_not_matched(): void
    {
        $user = User::factory()->create();
        $legit = Organization::create(['name' => 'Acme SA', 'currency' => 'CHF']);
        $legit->users()->attach($user->id, ['role' => 'owner']);

        $this->artisan('gaeld:cleanup-spam-orgs', ['--force' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('organizations', ['id' => $legit->id, 'deleted_at' => null]);
    }
}
