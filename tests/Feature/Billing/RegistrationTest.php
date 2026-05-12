<?php

namespace Tests\Feature\Billing;

use App\Domains\Accounting\Models\Account;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Mockery;
use Plugins\GaeldEE\Domains\Billing\Models\Plan;
use Plugins\GaeldEE\Domains\Billing\Models\Subscription;
use Plugins\GaeldEE\Domains\Billing\Services\BillingService;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Plan::class)) {
            $this->markTestSkipped('Gaeld EE plugin is not installed.');
        }

        config()->set('features.saas', true);

        // Avoid hitting the haveibeenpwned API in tests.
        Password::defaults(fn () => Password::min(12)->letters()->mixedCase()->numbers()->symbols());
    }

    public function test_signup_with_paid_plan_creates_trialing_subscription_and_seeds_chart_of_accounts(): void
    {
        $billing = Mockery::mock(BillingService::class);
        $billing->shouldReceive('createCheckoutSession')
            ->once()
            ->andReturn('https://checkout.stripe.test/session');
        $this->app->instance(BillingService::class, $billing);

        $plan = Plan::where('slug', 'business')->first()
            ?? Plan::create([
                'id' => (string) Str::uuid(),
                'name' => 'Business',
                'slug' => 'business',
                'price_chf' => 29.00,
                'stripe_price_id' => 'price_test_business',
                'max_users' => -1,
                'max_invoices_per_month' => -1,
                'features' => [],
                'is_active' => true,
                'sort_order' => 2,
            ]);

        // Force a non-zero price so the controller treats this as paid even
        // when the seeded business plan has been overridden in fixtures.
        $plan->forceFill(['price_chf' => 29.00, 'stripe_price_id' => 'price_test_business'])->save();

        $response = $this->post('/signup', [
            'name' => 'Alice Example',
            'email' => 'alice@example.test',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'org_name' => 'Acme AG',
            'plan_id' => $plan->id,
            'accepted_privacy' => true,
            'chart_of_accounts' => 'swiss_sme',
        ]);

        // Paid plan triggers a redirect to the Stripe Checkout session URL.
        $response->assertRedirect('https://checkout.stripe.test/session');

        $subscription = Subscription::firstOrFail();
        $this->assertSame('trialing', $subscription->status);
        $this->assertNotNull($subscription->trial_ends_at);

        $this->assertTrue(
            Account::where('organization_id', $subscription->organization_id)
                ->where('is_system', true)
                ->exists(),
            'Expected at least one system account to be seeded.',
        );
    }

    public function test_signup_with_free_plan_creates_active_subscription_without_trial(): void
    {
        $plan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Free',
            'slug' => 'free-test',
            'price_chf' => 0.00,
            'stripe_price_id' => null,
            'max_users' => 1,
            'max_invoices_per_month' => 5,
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->post('/signup', [
            'name' => 'Bob Example',
            'email' => 'bob@example.test',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'org_name' => 'Bob LLC',
            'plan_id' => $plan->id,
            'accepted_privacy' => true,
            'chart_of_accounts' => 'none',
        ]);

        $response->assertRedirect(route('dashboard'));

        $subscription = Subscription::where('plan_id', $plan->id)->firstOrFail();
        $this->assertSame('active', $subscription->status);
        $this->assertNull($subscription->trial_ends_at);
    }

    public function test_signup_returns_503_when_registration_is_closed(): void
    {
        config()->set('ee.registration', 'closed');

        $this->get('/signup')->assertStatus(503);
        $this->post('/signup', [])->assertStatus(503);
    }

    public function test_signup_rejects_free_plan_when_payment_is_required(): void
    {
        config()->set('ee.registration_require_payment', true);

        $freePlan = Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Free',
            'slug' => 'free-gate-test',
            'price_chf' => 0.00,
            'stripe_price_id' => null,
            'max_users' => 1,
            'max_invoices_per_month' => 5,
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->post('/signup', [
            'name' => 'Charlie Example',
            'email' => 'charlie@example.test',
            'password' => 'Password!123',
            'password_confirmation' => 'Password!123',
            'org_name' => 'Charlie Corp',
            'plan_id' => $freePlan->id,
            'accepted_privacy' => true,
            'chart_of_accounts' => 'none',
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('users', ['email' => 'charlie@example.test']);
    }

    public function test_signup_page_hides_free_plans_when_payment_is_required(): void
    {
        config()->set('ee.registration_require_payment', true);

        Plan::create([
            'id' => (string) Str::uuid(),
            'name' => 'Free',
            'slug' => 'free-hidden-test',
            'price_chf' => 0.00,
            'stripe_price_id' => null,
            'max_users' => 1,
            'max_invoices_per_month' => 5,
            'features' => [],
            'is_active' => true,
            'sort_order' => 0,
        ]);

        $response = $this->get('/signup');
        $response->assertStatus(200);

        $plans = $response->original->getData()['page']['props']['plans'] ?? [];
        foreach ($plans as $plan) {
            $this->assertGreaterThan(0, $plan['price_chf'], 'Free plan should not appear when payment is required.');
        }
    }
}
