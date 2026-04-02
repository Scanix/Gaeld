<?php

namespace Tests\Feature\Reporting;

use App\Domains\Contacts\Models\Customer;
use App\Domains\Expenses\Enums\ExpenseStatus;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Reporting\Services\AgingReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class AgingReportTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Customer $customer;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow('2026-03-20 12:00:00');

        $this->setUpOrganization();

        $this->customer = Customer::create([
            'organization_id' => $this->organization->id,
            'name' => 'Test Customer AG',
        ]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function createInvoice(string $issueDate, string $dueDate, InvoiceStatus $status = InvoiceStatus::Sent, float $total = 1000.00): Invoice
    {
        return Invoice::create([
            'organization_id' => $this->organization->id,
            'customer_id' => $this->customer->id,
            'number' => 'INV-'.uniqid(),
            'status' => $status->value,
            'issue_date' => $issueDate,
            'due_date' => $dueDate,
            'subtotal' => $total,
            'vat_amount' => 0,
            'total' => $total,
            'currency' => 'CHF',
        ]);
    }

    private function createExpense(string $date, ExpenseStatus $status = ExpenseStatus::Pending, float $amount = 500.00): Expense
    {
        return Expense::create([
            'organization_id' => $this->organization->id,
            'category' => 'test',
            'description' => 'Test expense',
            'amount' => $amount,
            'vat_amount' => 0,
            'date' => $date,
            'vendor' => 'Test Vendor',
            'status' => $status->value,
            'currency' => 'CHF',
        ]);
    }

    // asOf date is 2026-03-20 (Carbon::setTestNow above)

    public function test_aging_report_returns_expected_structure(): void
    {
        /** @var AgingReportService $service */
        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertEquals('receivables', $report['type']);
        $this->assertArrayHasKey('brackets', $report);
        $this->assertArrayHasKey('current', $report['brackets']);
        $this->assertArrayHasKey('1_30', $report['brackets']);
        $this->assertArrayHasKey('31_60', $report['brackets']);
        $this->assertArrayHasKey('61_90', $report['brackets']);
        $this->assertArrayHasKey('90_plus', $report['brackets']);
        $this->assertArrayHasKey('grand_total', $report);
    }

    public function test_current_bucket_contains_not_yet_due_invoices(): void
    {
        // Due in the future (currently asOf = 2026-03-20)
        $this->createInvoice('2026-03-01', '2026-03-25');

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertCount(1, $report['brackets']['current']['items']);
        $this->assertEquals(0, $report['brackets']['current']['items'][0]['days_overdue']);
    }

    public function test_1_30_bucket_contains_invoices_1_to_30_days_overdue(): void
    {
        // 15 days overdue: due 2026-03-05, asOf 2026-03-20
        $this->createInvoice('2026-02-01', '2026-03-05');

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertCount(1, $report['brackets']['1_30']['items']);
        $this->assertEquals(15, $report['brackets']['1_30']['items'][0]['days_overdue']);
    }

    public function test_31_60_bucket_contains_invoices_31_to_60_days_overdue(): void
    {
        // 45 days overdue: due 2026-02-03, asOf 2026-03-20
        $this->createInvoice('2026-01-01', '2026-02-03');

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertCount(1, $report['brackets']['31_60']['items']);
        $this->assertEquals(45, $report['brackets']['31_60']['items'][0]['days_overdue']);
    }

    public function test_90_plus_bucket_contains_very_overdue_invoices(): void
    {
        // 100+ days overdue: due 2025-12-10 → ~100 days overdue as of 2026-03-20
        $this->createInvoice('2025-11-01', '2025-12-10');

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertCount(1, $report['brackets']['90_plus']['items']);
        $this->assertGreaterThan(90, $report['brackets']['90_plus']['items'][0]['days_overdue']);
    }

    public function test_grand_total_equals_sum_of_bracket_totals(): void
    {
        $this->createInvoice('2026-03-01', '2026-03-25', total: 1000.00); // current
        $this->createInvoice('2026-02-01', '2026-03-05', total: 500.00);  // 1-30
        $this->createInvoice('2026-01-01', '2025-12-10', total: 250.00);  // 90+

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $sumOfBrackets = array_reduce($report['brackets'], fn ($c, $b) => bcadd($c, $b['total'], 2), '0.00');
        $this->assertEquals($sumOfBrackets, $report['grand_total']);
    }

    public function test_paid_invoices_are_excluded(): void
    {
        $this->createInvoice('2026-01-01', '2026-02-01', InvoiceStatus::Paid);

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'receivables');

        $this->assertEquals('0.00', $report['grand_total']);
    }

    public function test_payables_returns_expenses_not_fully_paid(): void
    {
        $this->createExpense('2026-03-10');

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'payables');

        $this->assertEquals('payables', $report['type']);
        $this->assertGreaterThan(0, count(array_merge(...array_column($report['brackets'], 'items'))));
    }

    public function test_posted_expenses_are_excluded_from_payables(): void
    {
        $this->createExpense('2026-03-10', ExpenseStatus::Posted);

        $service = app(AgingReportService::class);
        $report = $service->generate($this->organization->id, 'payables');

        $this->assertEquals('0.00', $report['grand_total']);
    }

    // ──────────────────────────────────────────────────────────────
    //  HTTP route tests
    // ──────────────────────────────────────────────────────────────

    public function test_aging_route_returns_inertia_response(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.aging', ['type' => 'receivables']));

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page->component('Reports/Aging'));
    }

    public function test_aging_export_pdf_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.aging.export', ['format' => 'pdf', 'type' => 'receivables']));

        $response->assertStatus(200);
        $this->assertStringContainsString('application/pdf', $response->headers->get('Content-Type'));
    }

    public function test_aging_export_csv_returns_correct_content_type(): void
    {
        $response = $this->actingAs($this->user)
            ->withSession(['current_organization_id' => $this->organization->id])
            ->get(route('reports.aging.export', ['format' => 'csv', 'type' => 'receivables']));

        $response->assertStatus(200);
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-Type'));
    }
}
