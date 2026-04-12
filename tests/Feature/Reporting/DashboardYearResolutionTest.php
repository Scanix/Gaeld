<?php

namespace Tests\Feature\Reporting;

use App\Domains\Expenses\Actions\ApproveExpenseAction;
use App\Domains\Expenses\Actions\CreateExpenseAction;
use App\Domains\Expenses\DTOs\CreateExpenseData;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Reporting\Services\DashboardService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class DashboardYearResolutionTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private DashboardService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
        $this->service = app(DashboardService::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  resolveDisplayYear — no posted journal entries
    // ──────────────────────────────────────────────────────────────

    public function test_display_year_resolves_to_prior_year_when_expense_dated_in_prior_year(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'Clef local',
            'amount' => '76.60',
            'vat_amount' => '0.00',
            'date' => '2025-12-16',
            'status' => 'approved',
            'currency' => 'CHF',
        ]);

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2025, $metrics['displayYear']);
        $this->assertEquals('76.60', $metrics['expenses']);

        Carbon::setTestNow();
    }

    public function test_display_year_resolves_to_current_year_when_expense_dated_in_current_year(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'Office rent',
            'amount' => '1200.00',
            'vat_amount' => '0.00',
            'date' => '2026-03-01',
            'status' => 'approved',
            'currency' => 'CHF',
        ]);

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2026, $metrics['displayYear']);
        $this->assertEquals('1200.00', $metrics['expenses']);

        Carbon::setTestNow();
    }

    public function test_display_year_uses_most_recent_when_both_years_have_expenses(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        // 2025 expense
        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'Old expense',
            'amount' => '500.00',
            'vat_amount' => '0.00',
            'date' => '2025-06-01',
            'status' => 'approved',
            'currency' => 'CHF',
        ]);

        // 2026 expense
        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'New expense',
            'amount' => '300.00',
            'vat_amount' => '0.00',
            'date' => '2026-01-15',
            'status' => 'approved',
            'currency' => 'CHF',
        ]);

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2026, $metrics['displayYear']);
        $this->assertEquals('300.00', $metrics['expenses']);

        Carbon::setTestNow();
    }

    public function test_display_year_is_capped_at_current_year_even_if_future_expense_exists(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'Future expense',
            'amount' => '100.00',
            'vat_amount' => '0.00',
            'date' => '2027-01-01',
            'status' => 'pending',
            'currency' => 'CHF',
        ]);

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2026, $metrics['displayYear']);

        Carbon::setTestNow();
    }

    public function test_display_year_falls_back_to_current_year_when_no_activity(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2026, $metrics['displayYear']);
        $this->assertEquals('0.00', $metrics['expenses']);

        Carbon::setTestNow();
    }

    public function test_display_year_resolves_to_invoice_year_when_only_invoices_exist(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        Invoice::create([
            'organization_id' => $this->org->id,
            'number' => 'INV-2025-001',
            'status' => 'sent',
            'issue_date' => '2025-11-01',
            'due_date' => '2025-12-01',
            'subtotal' => '2000.00',
            'vat_amount' => '0.00',
            'total' => '2000.00',
            'currency' => 'CHF',
        ]);

        $metrics = $this->service->metrics($this->org->id);

        $this->assertEquals(2025, $metrics['displayYear']);

        Carbon::setTestNow();
    }

    // ──────────────────────────────────────────────────────────────
    //  Cache invalidation
    // ──────────────────────────────────────────────────────────────

    public function test_dashboard_cache_flushed_after_expense_created_via_action(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        // Prime the cache with empty metrics
        $first = $this->service->metrics($this->org->id);
        $this->assertEquals('0.00', $first['expenses']);

        // Create expense and flush cache (as the controller would)
        $action = new CreateExpenseAction;
        $action->execute(CreateExpenseData::fromArray([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Software and Subscriptions',
            'description' => 'New tool',
            'amount' => '150.00',
            'vat_amount' => '0.00',
            'date' => '2026-04-01',
        ]));
        $this->service->flushCache($this->org->id);

        // Re-fetch should reflect the new expense
        $second = $this->service->metrics($this->org->id);
        $this->assertEquals('150.00', $second['expenses']);
        $this->assertEquals(2026, $second['displayYear']);

        Carbon::setTestNow();
    }

    public function test_dashboard_cache_flushed_after_expense_approved(): void
    {
        Carbon::setTestNow('2026-04-12 10:00:00');

        $expense = Expense::create([
            'organization_id' => $this->org->id,
            'user_id' => $this->user->id,
            'category' => 'Utilities',
            'description' => 'Pending expense',
            'amount' => '500.00',
            'vat_amount' => '0.00',
            'date' => '2026-02-15',
            'status' => 'pending',
            'currency' => 'CHF',
        ]);

        // Prime cache
        $this->service->metrics($this->org->id);

        // Approve and flush cache (as the controller would)
        $approveAction = new ApproveExpenseAction;
        $approveAction->execute($expense);
        $this->service->flushCache($this->org->id);

        $metrics = $this->service->metrics($this->org->id);
        $this->assertEquals('500.00', $metrics['expenses']);

        Carbon::setTestNow();
    }
}
