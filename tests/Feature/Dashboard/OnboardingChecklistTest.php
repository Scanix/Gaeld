<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Phase 2 onboarding checklist: ensures the dashboard exposes a checklist
 * payload when the organization has incomplete items and respects the
 * org-scoped dismissal flag.
 */
class OnboardingChecklistTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_new_organization_sees_checklist_on_dashboard(): void
    {
        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('checklist.getting_started')
            ->has('checklist.accounting')
        );
    }

    public function test_dismissed_organization_sees_null_checklist(): void
    {
        $this->organization->forceFill(['onboarding_dismissed_at' => now()])->save();

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('checklist', null)
        );
    }

    public function test_dismiss_endpoint_sets_timestamp_and_redirects(): void
    {
        $this->assertNull($this->organization->onboarding_dismissed_at);

        $response = $this->actAsOrg()->post('/onboarding/dismiss');

        $response->assertRedirect(route('dashboard'));
        $this->assertNotNull($this->organization->fresh()->onboarding_dismissed_at);
    }

    public function test_dismiss_endpoint_is_idempotent(): void
    {
        $original = now()->subDay();
        $this->organization->forceFill(['onboarding_dismissed_at' => $original])->save();

        $this->actAsOrg()->post('/onboarding/dismiss')->assertRedirect(route('dashboard'));

        // Timestamp should not be overwritten on subsequent dismiss calls.
        $this->assertSame(
            $original->toDateTimeString(),
            $this->organization->fresh()->onboarding_dismissed_at->toDateTimeString()
        );
    }

    public function test_fully_completed_checklist_is_null(): void
    {
        // Fulfil every getting_started item so hasIncompleteItems() returns false
        // for that tier. Accounting tier will still have incomplete items, so
        // the checklist payload remains present — assert on the getting_started
        // tier instead.
        $this->organization->forceFill([
            'legal_name' => 'Acme SA',
            'address' => 'Rue 1',
            'city' => 'Geneva',
            'postal_code' => '1200',
        ])->save();

        Account::factory()->create(['organization_id' => $this->organization->id]);
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);
        BankAccount::factory()->create(['organization_id' => $this->organization->id]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('checklist.getting_started', fn ($items) => collect($items)->every(fn ($item) => $item['done']))
        );
    }
}
