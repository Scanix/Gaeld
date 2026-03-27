<?php

namespace Tests\Feature;

use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationSetupFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_page_renders_for_guest_without_organization(): void
    {
        $response = $this->get('/setup');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Setup/Wizard'));
    }

    public function test_guest_setup_creates_user_organization_and_default_seed_data(): void
    {
        $response = $this->post('/setup', [
            'user_name' => 'Setup Owner',
            'user_email' => 'setup@example.com',
            'user_password' => 'password123',
            'user_password_confirmation' => 'password123',
            'org_name' => 'Setup Org',
            'org_legal_name' => 'Setup Org SA',
            'org_address' => 'Main Street 1',
            'org_city' => 'Lausanne',
            'org_postal_code' => '1000',
            'org_canton' => 'VD',
            'currency' => 'CHF',
            'locale' => 'en',
        ]);

        $organization = Organization::where('name', 'Setup Org')->firstOrFail();
        $user = User::where('email', 'setup@example.com')->firstOrFail();

        $response->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertGreaterThan(0, Account::where('organization_id', $organization->id)->count());
        $this->assertGreaterThan(0, VatRate::where('organization_id', $organization->id)->count());
    }

    public function test_verified_user_onboarding_creates_org_sets_session_and_seeds_defaults(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        /** @var User $user */
        $response = $this->actingAs($user)->post('/onboarding', [
            'name' => 'Onboarding Org',
            'legal_name' => 'Onboarding Org AG',
            'address' => 'Bahnhofstrasse 1',
            'city' => 'Zurich',
            'postal_code' => '8001',
            'canton' => 'ZH',
            'vat_number' => 'CHE-123.456.789',
            'currency' => 'CHF',
            'locale' => 'en',
            'chart_of_accounts' => 'swiss_sme',
        ]);

        $organization = Organization::where('name', 'Onboarding Org')->firstOrFail();

        $response->assertRedirect('/');
        $response->assertSessionHas('current_organization_id', $organization->id);
        $this->assertDatabaseHas('organization_users', [
            'organization_id' => $organization->id,
            'user_id' => $user->id,
            'role' => 'owner',
        ]);
        $this->assertGreaterThan(0, Account::where('organization_id', $organization->id)->count());
        $this->assertGreaterThan(0, VatRate::where('organization_id', $organization->id)->count());
    }

    public function test_setup_redirects_when_organization_already_exists(): void
    {
        Organization::create([
            'name' => 'Existing Org',
            'currency' => 'CHF',
        ]);

        $response = $this->get('/setup');

        $response->assertRedirect('/');
    }

    public function test_verified_user_without_org_is_redirected_to_onboarding_from_dashboard(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        /** @var User $user */
        $response = $this->actingAs($user)->get('/');

        $response->assertRedirect('/onboarding');
    }

    public function test_onboarding_page_redirects_when_user_already_has_organization(): void
    {
        $user = User::factory()->create([
            'email_verified_at' => now(),
        ]);
        /** @var User $user */
        $organization = Organization::create([
            'name' => 'Existing Membership Org',
            'currency' => 'CHF',
        ]);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        $response = $this->actingAs($user)
            ->withSession(['current_organization_id' => $organization->id])
            ->get('/onboarding');

        $response->assertRedirect('/');
    }
}
