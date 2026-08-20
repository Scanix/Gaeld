<?php

namespace Tests\Unit\Organizations;

use App\Domains\Organizations\Events\MemberRemoved;
use App\Domains\Organizations\Listeners\RevokeOrganizationTokens;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * RevokeOrganizationTokens previously had zero test coverage despite being
 * a real security control: it revokes a removed member's API tokens scoped
 * to that organization. A bug here (wrong scoping column, wrong event
 * property) would let a removed member's token keep working against the
 * organization's data after removal.
 */
class RevokeOrganizationTokensTest extends TestCase
{
    use RefreshDatabase;

    public function test_revokes_only_tokens_scoped_to_the_removed_organization(): void
    {
        $user = User::factory()->create();
        $orgA = Organization::create(['name' => 'Org A', 'currency' => 'CHF']);
        $orgB = Organization::create(['name' => 'Org B', 'currency' => 'CHF']);

        app(CurrentOrganization::class)->set($orgA);
        $tokenA = $user->createToken('token-a', ['*'])->accessToken;

        app(CurrentOrganization::class)->set($orgB);
        $tokenB = $user->createToken('token-b', ['*'])->accessToken;

        $this->assertSame($orgA->id, $tokenA->organization_id);
        $this->assertSame($orgB->id, $tokenB->organization_id);

        (new RevokeOrganizationTokens)->handle(new MemberRemoved($orgA, $user));

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $tokenA->id]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $tokenB->id]);
    }

    public function test_does_nothing_when_user_has_no_tokens_for_the_organization(): void
    {
        $user = User::factory()->create();
        $org = Organization::create(['name' => 'Org Empty', 'currency' => 'CHF']);

        (new RevokeOrganizationTokens)->handle(new MemberRemoved($org, $user));

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }
}
