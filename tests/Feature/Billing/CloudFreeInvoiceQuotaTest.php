<?php

namespace Tests\Feature\Billing;

use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Cache;
use Plugins\GaeldEE\Domains\Billing\Models\Plan;
use Plugins\GaeldEE\Domains\Billing\Models\Subscription;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class CloudFreeInvoiceQuotaTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $owner;

    private Organization $organization;

    private Contact $customer;

    private VatRate $vatRate;

    public function createApplication(): Application
    {
        $_ENV['APP_BASE_PATH'] = realpath(__DIR__.'/../../..');
        $_ENV['PLUGINS_ENABLED'] = 'true';
        RefreshDatabaseState::$migrated = false;
        $app = parent::createApplication();
        $_ENV['PLUGINS_ENABLED'] = 'false';

        return $app;
    }

    protected function setUp(): void
    {
        parent::setUp();

        if (! class_exists(Plan::class)) {
            $this->markTestSkipped('Enterprise Edition is not enabled.');
        }

        config()->set('features.saas', true);
        $this->seedPermissions();

        $this->owner = User::factory()->create(['onboarding_completed_at' => now()]);
        $this->organization = Organization::factory()->create();
        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');
        $this->customer = Contact::create([
            'organization_id' => $this->organization->id,
            'name' => 'Cloud Free quota customer',
        ]);
        $this->vatRate = VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.1,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);

        Subscription::create([
            'organization_id' => $this->organization->id,
            'plan_id' => Plan::cloudFree()->id,
            'status' => 'active',
        ]);
    }

    protected function tearDown(): void
    {
        parent::tearDown();

        RefreshDatabaseState::$migrated = false;
    }

    public function test_cloud_free_rejects_the_sixth_invoice_in_the_current_month(): void
    {
        $monthlyKey = 'invoices_monthly:'.$this->organization->id.':'.now()->format('Y-m');
        Cache::forget($monthlyKey);

        $payload = [
            'customer_id' => $this->customer->id,
            'issue_date' => now()->toDateString(),
            'due_date' => now()->addDays(30)->toDateString(),
            'tax_treatment' => 'standard',
            'lines' => [[
                'description' => 'Cloud Free quota test',
                'quantity' => 1,
                'unit_price' => 10,
                'vat_rate_id' => $this->vatRate->id,
            ]],
        ];

        for ($invoiceNumber = 1; $invoiceNumber <= 5; $invoiceNumber++) {
            $this->actingAs($this->owner)
                ->withSession(['current_organization_id' => $this->organization->id])
                ->post('/invoices', $payload)
                ->assertRedirect();
        }

        $this->actingAs($this->owner)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->post('/invoices', $payload)
            ->assertRedirect()
            ->assertSessionHas('error', __('app.invoice_monthly_limit_reached'));

        $this->assertSame(5, Invoice::query()->where('organization_id', $this->organization->id)->count());
        $this->assertSame(5, (int) Cache::get($monthlyKey));
    }
}
