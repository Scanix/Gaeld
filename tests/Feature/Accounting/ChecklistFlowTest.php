<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Reporting\Services\ChecklistService;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class ChecklistFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private Organization $org;

    private User $user;

    private ChecklistService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->org = Organization::create([
            'name' => 'Checklist Test GmbH',
            'currency' => 'CHF',
        ]);
        $this->org->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->org, 'owner');

        $this->service = app(ChecklistService::class);
    }

    public function test_empty_org_returns_all_items_not_done(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        $this->assertCount(11, $checklist);

        foreach ($checklist as $item) {
            $this->assertFalse($item['done'], "Item {$item['key']} should be not done for empty org");
        }
    }

    public function test_checklist_returns_expected_keys(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        $keys = array_column($checklist, 'key');
        $this->assertContains('checklist_chart_configured', $keys);
        $this->assertContains('checklist_invoices_created', $keys);
        $this->assertContains('checklist_expenses_posted', $keys);
        $this->assertContains('checklist_bank_imported', $keys);
        $this->assertContains('checklist_reconciliation_done', $keys);
        $this->assertContains('checklist_vat_declared', $keys);
        $this->assertContains('checklist_depreciation_posted', $keys);
        $this->assertContains('checklist_social_charges', $keys);
        $this->assertContains('checklist_year_end_closed', $keys);
        $this->assertContains('checklist_fiduciary_exported', $keys);
        $this->assertContains('checklist_data_imported', $keys);
    }

    public function test_chart_configured_is_done_when_accounts_exist(): void
    {
        Account::create([
            'organization_id' => $this->org->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist)->firstWhere('key', 'checklist_chart_configured');

        $this->assertTrue($item['done']);
    }

    public function test_invoices_created_is_done_when_invoice_exists(): void
    {
        Account::create(['organization_id' => $this->org->id, 'code' => '1100', 'name' => 'AR', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '1020', 'name' => 'Bank', 'type' => AccountType::Asset->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '2200', 'name' => 'VAT Output', 'type' => AccountType::Liability->value]);
        Account::create(['organization_id' => $this->org->id, 'code' => '3900', 'name' => 'Rounding', 'type' => AccountType::Revenue->value]);

        $customer = Customer::create(['organization_id' => $this->org->id, 'name' => 'Client AG']);

        app(CreateInvoiceAction::class)->execute(CreateInvoiceData::fromArray([
            'organization_id' => $this->org->id,
            'customer_id' => $customer->id,
            'number' => 'INV-001',
            'issue_date' => '2026-01-15',
            'due_date' => '2026-02-15',
            'currency' => 'CHF',
            'lines' => [
                ['description' => 'Service', 'quantity' => '1', 'unit_price' => '500.00'],
            ],
        ]));

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist)->firstWhere('key', 'checklist_invoices_created');

        $this->assertTrue($item['done']);
    }

    public function test_expenses_posted_is_done_when_posted_expense_exists(): void
    {
        Expense::create([
            'organization_id' => $this->org->id,
            'description' => 'Office supplies',
            'category' => 'Office',
            'amount' => '150.00',
            'date' => '2026-01-10',
            'status' => 'posted',
        ]);

        $checklist = $this->service->checklist($this->org->id);
        $item = collect($checklist)->firstWhere('key', 'checklist_expenses_posted');

        $this->assertTrue($item['done']);
    }

    public function test_each_item_has_href(): void
    {
        $checklist = $this->service->checklist($this->org->id);

        foreach ($checklist as $item) {
            $this->assertArrayHasKey('href', $item, "Item {$item['key']} should have an href");
            $this->assertNotEmpty($item['href']);
        }
    }
}
