<?php

namespace Tests\Feature;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\VatRate;
use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\Actions\PostExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Invoicing\Actions\CreateInvoiceAction;
use App\Domains\Invoicing\Actions\FinalizeInvoiceAction;
use App\Domains\Invoicing\DTOs\CreateInvoiceData;
use App\Domains\Invoicing\DTOs\InvoiceLineData;
use App\Domains\Invoicing\DTOs\RecordPaymentData;
use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Domains\Invoicing\Services\InvoiceService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReportingFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-20 12:00:00');

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Reporting Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);

        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Accounts Receivable',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '6530',
            'name' => 'Software Expense',
            'type' => AccountType::Expense->value,
        ]);

        VatRate::create([
            'organization_id' => $this->organization->id,
            'name' => 'Standard',
            'rate' => 8.10,
            'code' => 'NORMAL',
            'is_default' => true,
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_route_returns_inertia_response(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->has('revenue')
            ->has('expenses')
            ->has('monthlyBreakdown.labels'));
    }

    public function test_profit_and_loss_route_returns_report_payload(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/reports/profit-and-loss?from=2026-01-01&to=2026-03-31');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/ProfitAndLoss')
            ->has('report')
            ->where('report.period.from', '2026-01-01')
            ->where('report.period.to', '2026-03-31'));
    }

    public function test_balance_sheet_route_returns_report_payload(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/reports/balance-sheet?as_of_date=2026-03-31');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Reports/BalanceSheet')
            ->has('report')
            ->where('report.as_of_date', '2026-03-31'));
    }

    public function test_reporting_routes_reflect_real_financial_activity(): void
    {
        $this->seedFinancialActivity();

        $dashboard = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/');

        $dashboard->assertStatus(200);
        $dashboard->assertInertia(fn ($page) => $page
            ->component('Dashboard')
            ->where('revenue', '1081.00')
            ->where('expenses', '200.00')
            ->where('cashBalance', '881.00')
            ->where('balance', '881.00'));

        $profitAndLoss = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get('/reports/profit-and-loss?from=2026-01-01&to=2026-12-31');

        $profitAndLoss->assertStatus(200);
        $profitAndLoss->assertInertia(fn ($page) => $page
            ->component('Reports/ProfitAndLoss')
            ->where('report.total_revenue', 1081)
            ->where('report.total_expenses', 200)
            ->where('report.net_profit', '881.00'));
    }

    private function seedFinancialActivity(): void
    {
        $customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Acme AG',
        ]);

        $vatRateId = VatRate::where('organization_id', $this->organization->id)->value('id');

        $invoice = app(CreateInvoiceAction::class)->execute(new CreateInvoiceData(
            organizationId: $this->organization->id,
            customerId: $customer->id,
            number: 'INV-2026-300',
            issueDate: '2026-02-10',
            dueDate: '2026-03-10',
            currency: 'CHF',
            notes: null,
            paymentTerms: '30 days',
            lines: [
                InvoiceLineData::fromArray([
                    'description' => 'Consulting',
                    'quantity' => 2,
                    'unit_price' => 500.00,
                    'vat_rate_id' => $vatRateId,
                ]),
            ],
        ));

        $invoice = app(FinalizeInvoiceAction::class)->execute($invoice);
        app(InvoiceService::class)->recordPayment($invoice, new RecordPaymentData(
            amount: (string) $invoice->total,
            paymentDate: '2026-03-05',
            paymentMethod: PaymentMethod::Bank,
            reference: 'PAY-INV-2026-300',
        ));

        $expense = app(CreateExpenseAction::class)->execute(CreateExpenseData::fromArray([
            'organization_id' => $this->organization->id,
            'category' => 'Software',
            'description' => 'Hosting',
            'amount' => 200.00,
            'vat_amount' => 0,
            'date' => '2026-03-06',
            'vendor' => 'Hetzner',
            'currency' => 'CHF',
        ]));

        $expense = app(ApproveExpenseAction::class)->execute($expense);
        app(PostExpenseAction::class)->execute($expense, '6530');
    }
}