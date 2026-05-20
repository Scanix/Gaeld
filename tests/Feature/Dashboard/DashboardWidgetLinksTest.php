<?php

namespace Tests\Feature\Dashboard;

use App\Domains\Contacts\Models\Contact;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

/**
 * Phase 1 regression guard: dashboard CTAs link to filtered list pages and
 * those filters are honoured by InvoiceQuery / ExpenseQuery.
 */
class DashboardWidgetLinksTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    public function test_invoices_filter_status_sent_returns_only_sent_invoices(): void
    {
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);

        Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        Invoice::factory()->paid()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        Invoice::factory()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]); // draft

        $response = $this->actAsOrg()->get('/invoices?filter[status]=sent');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->where('invoices.data', fn (Collection $data) => $data->count() === 1
                && $data->every(
                    fn (array $invoice) => $invoice['status'] === InvoiceStatus::Sent->value
                ))
        );
    }

    public function test_invoices_filter_status_overdue_returns_only_overdue_invoices(): void
    {
        $customer = Contact::factory()->create(['organization_id' => $this->organization->id]);

        Invoice::factory()->overdue()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);
        Invoice::factory()->sent()->create([
            'organization_id' => $this->organization->id,
            'customer_id' => $customer->id,
        ]);

        $response = $this->actAsOrg()->get('/invoices?filter[status]=overdue');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Invoices/Index')
            ->where('invoices.data', fn (Collection $data) => $data->count() === 1
                && $data->every(
                    fn (array $invoice) => $invoice['status'] === InvoiceStatus::Overdue->value
                ))
        );
    }

    public function test_expenses_filter_status_pending_returns_only_pending_expenses(): void
    {
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ExpenseStatus::Pending->value,
        ]);
        Expense::factory()->create([
            'organization_id' => $this->organization->id,
            'status' => ExpenseStatus::Approved->value,
        ]);

        $response = $this->actAsOrg()->get('/expenses?filter[status]=pending');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Expenses/Index')
            ->where('expenses.data', fn (Collection $data) => $data->count() === 1
                && $data->every(
                    fn (array $expense) => $expense['status'] === ExpenseStatus::Pending->value
                ))
        );
    }
}
