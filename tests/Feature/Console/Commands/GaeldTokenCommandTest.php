<?php

namespace Tests\Feature\Console\Commands;

use App\Domains\Api\Enums\TokenType;
use App\Domains\Api\Models\PersonalAccessToken;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class GaeldTokenCommandTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    public function test_it_creates_an_organization_token_for_an_owner(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create(['email' => 'owner@example.test']);
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $this->runTokenCommand([
            'organization' => $organization->id,
            '--user' => $user->email,
            '--name' => 'Provisioning token',
            '--abilities' => [Permission::BankingCreate->value, Permission::BankingImport->value],
            '--expires-in-days' => '30',
        ])
            ->expectsOutputToContain('API token created successfully.')
            ->assertSuccessful();

        $token = PersonalAccessToken::query()->where('name', 'Provisioning token')->firstOrFail();

        $this->assertSame($organization->id, $token->organization_id);
        $this->assertSame(TokenType::Organization, $token->type);
        $this->assertSame([
            Permission::BankingCreate->value,
            Permission::BankingImport->value,
        ], $token->abilities);
        $this->assertSame($token->created_at->copy()->addDays(30)->toDateString(), $token->expires_at->toDateString());
    }

    public function test_it_defaults_to_the_first_owner_and_full_access(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $this->runTokenCommand(['organization' => $organization->id])
            ->assertSuccessful();

        $token = PersonalAccessToken::query()->where('tokenable_id', $user->id)->firstOrFail();
        $this->assertSame(['*'], $token->abilities);
        $this->assertSame(TokenType::Organization, $token->type);
    }

    public function test_it_rejects_invalid_abilities_and_expiration_without_creating_a_token(): void
    {
        $this->seedPermissions();
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($user, $organization, 'owner');

        $this->runTokenCommand([
            'organization' => $organization->id,
            '--abilities' => ['not-a-real-ability'],
        ])->assertFailed();

        $this->runTokenCommand([
            'organization' => $organization->id,
            '--expires-in-days' => '366',
        ])->assertFailed();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function runTokenCommand(array $arguments): PendingCommand
    {
        $command = $this->artisan('gaeld:token', $arguments);

        if (! $command instanceof PendingCommand) {
            self::fail('The token command did not return a pending command.');
        }

        return $command;
    }
}
