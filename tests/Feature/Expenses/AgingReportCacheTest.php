<?php

namespace Tests\Feature\Expenses;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class AgingReportCacheTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();
    }

    /**
     * Regression test: creating a new expense used to only flush the
     * "dashboard" cache tag, not "reports" — the Payables Aging report
     * (which depends on pending/approved expense state directly) could stay
     * stale for up to 30 minutes. DashboardService::flushCache() now flushes
     * both tags.
     */
    public function test_new_expense_appears_in_payables_aging_immediately(): void
    {
        // Warm the aging cache with an empty result.
        $before = $this->actAsOrg()->get('/reports/aging?type=payables');
        $before->assertInertia(fn ($page) => $page->where('report.rows', []));

        $this->actAsOrg()->post('/expenses', [
            'category' => 'Software',
            'amount' => 250.00,
            'date' => now()->toDateString(),
            'vendor' => 'Test Vendor',
        ])->assertRedirect();

        $after = $this->actAsOrg()->get('/reports/aging?type=payables');
        $after->assertInertia(fn ($page) => $page->where('report.rows.0.name', 'Test Vendor'));
    }
}
