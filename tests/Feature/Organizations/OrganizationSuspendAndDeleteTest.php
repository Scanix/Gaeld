<?php

namespace Tests\Feature\Organizations;

use App\Domains\Organizations\Actions\DeleteOrganizationAction;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Http\Middleware\EnsureHasOrganization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class OrganizationSuspendAndDeleteTest extends TestCase
{
    use RefreshDatabase;

    public function test_is_suspended_reflects_suspended_at(): void
    {
        $org = Organization::create(['name' => 'Acme', 'currency' => 'CHF']);

        $this->assertFalse($org->isSuspended());

        $org->suspend('abuse');

        $this->assertTrue($org->fresh()->isSuspended());
        $this->assertSame('abuse', $org->fresh()->suspended_reason);

        $org->reactivate();

        $this->assertFalse($org->fresh()->isSuspended());
        $this->assertNull($org->fresh()->suspended_reason);
    }

    public function test_suspended_org_blocks_normal_user(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        $org = Organization::create(['name' => 'BlockMe', 'currency' => 'CHF']);
        $org->users()->attach($user->id, ['role' => 'owner']);
        $org->suspend('non-payment');

        $middleware = app(EnsureHasOrganization::class);
        $request = Request::create('/dashboard');
        $request->setUserResolver(fn () => $user);

        $this->expectException(HttpException::class);

        try {
            $middleware->handle($request, fn ($r) => new Response('ok'));
        } catch (HttpException $e) {
            $this->assertSame(403, $e->getStatusCode());
            throw $e;
        }
    }

    public function test_delete_action_soft_deletes_and_orphans_users(): void
    {
        $owner = User::factory()->create();
        $org = Organization::create(['name' => 'Spammy', 'currency' => 'CHF']);
        $org->users()->attach($owner->id, ['role' => 'owner']);

        app(DeleteOrganizationAction::class)->execute($org, 'test');

        $this->assertSoftDeleted('organizations', ['id' => $org->id]);
        $this->assertDatabaseMissing('organization_users', ['organization_id' => $org->id]);
        $this->assertDatabaseHas('users', ['id' => $owner->id]);
    }

    public function test_delete_action_keeps_user_with_other_memberships(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::create(['name' => 'A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'B', 'currency' => 'CHF']);
        $orgA->users()->attach($user->id, ['role' => 'owner']);
        $orgB->users()->attach($user->id, ['role' => 'owner']);

        app(DeleteOrganizationAction::class)->execute($orgA);

        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseHas('organization_users', [
            'user_id' => $user->id,
            'organization_id' => $orgB->id,
        ]);
    }
}
