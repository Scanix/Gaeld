<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Contact;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithActiveSubscription;
use Tests\Traits\WithOrganizationPermissions;

class YearEndClosingWizardTest extends TestCase
{
    use RefreshDatabase, WithActiveSubscription, WithOrganizationPermissions;

    private User $owner;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->owner = User::factory()->create();

        $this->organization = Organization::create([
            'name' => 'Wizard Test Org',
            'currency' => 'CHF',
        ]);

        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->owner, $this->organization, 'owner');

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Debtors',
            'type' => AccountType::Asset->value,
        ]);

        $this->ensureSubscriptionIfSaas($this->organization);
    }

    public function test_index_returns_outstanding_invoices_prop(): void
    {
        $response = $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ])->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/YearEndClosing')
            ->has('outstandingInvoices')
        );
    }

    public function test_outstanding_invoices_contain_sent_and_overdue_for_year(): void
    {
        $customer = Contact::factory()->create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme Inc.',
            'email' => 'billing@acme.test',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2025-001',
            'status' => InvoiceStatus::Sent,
            'issue_date' => '2025-06-15',
            'due_date' => '2025-07-15',
            'total' => '500.00',
        ]);

        Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
            'number' => 'INV-2025-002',
            'status' => InvoiceStatus::Paid,
            'issue_date' => '2025-06-15',
            'due_date' => '2025-07-15',
            'total' => '300.00',
        ]);

        $response = $this->actingAs($this->owner)->withSession([
            'current_organization_id' => $this->organization->id,
        ])->get('/accounting/year-end-closing?year=2025');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/YearEndClosing')
            ->has('outstandingInvoices', 1)
            ->where('outstandingInvoices.0.number', 'INV-2025-001')
        );
    }
}
