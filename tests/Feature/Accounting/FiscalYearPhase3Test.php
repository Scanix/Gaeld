<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Notifications\FiscalYearExpiredNotification;
use App\Domains\Accounting\Services\FiscalYearService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class FiscalYearPhase3Test extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private FiscalYearService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->setUpOrganization();
        $this->service = app(FiscalYearService::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  markExpired() — notification firing
    // ──────────────────────────────────────────────────────────────

    public function test_mark_expired_fires_notification_for_org_members(): void
    {
        Notification::fake();

        $fy = FiscalYear::factory()->for($this->org)->operative()->create([
            'name' => '2023',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $count = $this->service->markExpired($this->org);

        $this->assertSame(1, $count);
        $fy->refresh();
        $this->assertSame(FiscalYearStatus::Expired, $fy->status);

        Notification::assertSentTo($this->user, FiscalYearExpiredNotification::class);
    }

    public function test_mark_expired_respects_notification_opt_out(): void
    {
        Notification::fake();

        $this->user->update([
            'notification_preferences' => ['fiscal_year_expired' => false],
        ]);

        FiscalYear::factory()->for($this->org)->operative()->create([
            'name' => '2023',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $this->service->markExpired($this->org);

        Notification::assertNothingSent();
    }

    public function test_mark_expired_skips_years_that_are_not_past_end_date(): void
    {
        Notification::fake();

        // Year still operative and not yet ended
        FiscalYear::factory()->for($this->org)->operative()->create([
            'name' => (string) (now()->year + 1),
            'start_date' => now()->addYear()->startOfYear()->toDateString(),
            'end_date' => now()->addYear()->endOfYear()->toDateString(),
        ]);

        $count = $this->service->markExpired($this->org);

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
    }

    public function test_mark_expired_returns_zero_when_no_operative_years(): void
    {
        Notification::fake();

        $count = $this->service->markExpired($this->org);

        $this->assertSame(0, $count);
        Notification::assertNothingSent();
    }

    // ──────────────────────────────────────────────────────────────
    //  markExpiredAll()
    // ──────────────────────────────────────────────────────────────

    public function test_mark_expired_all_processes_multiple_organisations(): void
    {
        Notification::fake();

        $org2 = Organization::factory()->create();
        $user2 = User::factory()->create();
        $org2->users()->attach($user2->id, ['role' => 'owner']);

        FiscalYear::factory()->for($this->org)->operative()->create([
            'start_date' => '2022-01-01',
            'end_date' => '2022-12-31',
        ]);

        FiscalYear::factory()->for($org2)->operative()->create([
            'start_date' => '2022-01-01',
            'end_date' => '2022-12-31',
        ]);

        $this->service->markExpiredAll();

        $this->assertSame(1, FiscalYear::withoutGlobalScope('organization')->where('organization_id', $this->org->id)->where('status', FiscalYearStatus::Expired->value)->count());
        $this->assertSame(1, FiscalYear::withoutGlobalScope('organization')->where('organization_id', $org2->id)->where('status', FiscalYearStatus::Expired->value)->count());

        Notification::assertSentTo($this->user, FiscalYearExpiredNotification::class);
        Notification::assertSentTo($user2, FiscalYearExpiredNotification::class);
    }

    // ──────────────────────────────────────────────────────────────
    //  close() — auto-create next year
    // ──────────────────────────────────────────────────────────────

    public function test_close_auto_creates_next_year_when_no_planned_year_exists(): void
    {
        $fy = FiscalYear::factory()->for($this->org)->operative()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $nextCreated = $this->service->close($fy, $this->user);

        $this->assertTrue($nextCreated);

        $next = FiscalYear::where('organization_id', $this->org->id)
            ->where('status', FiscalYearStatus::Planned->value)
            ->first();

        $this->assertNotNull($next);
        $this->assertSame('2025-01-01', $next->start_date->toDateString());
        $this->assertSame('2025-12-31', $next->end_date->toDateString());
    }

    public function test_close_returns_false_and_advances_existing_planned_year(): void
    {
        $current = FiscalYear::factory()->for($this->org)->operative()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);

        $planned = FiscalYear::factory()->for($this->org)->planned()->create([
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
        ]);

        $nextCreated = $this->service->close($current, $this->user);

        $this->assertFalse($nextCreated);

        $planned->refresh();
        $this->assertSame(FiscalYearStatus::Operative, $planned->status);

        // Should NOT have created an additional fiscal year
        $this->assertSame(2, FiscalYear::where('organization_id', $this->org->id)->count());
    }

    // ──────────────────────────────────────────────────────────────
    //  Dashboard: expired fiscal year banner prop
    // ──────────────────────────────────────────────────────────────

    public function test_dashboard_passes_expired_fiscal_year_when_present(): void
    {
        $fy = FiscalYear::factory()->for($this->org)->expired()->create([
            'name' => '2023',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('expiredFiscalYear.id', $fy->id)
                ->where('expiredFiscalYear.name', '2023')
        );
    }

    public function test_dashboard_passes_null_when_no_expired_fiscal_year(): void
    {
        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('expiredFiscalYear', null)
        );
    }

    public function test_dashboard_does_not_show_expired_year_when_already_closed(): void
    {
        // A closed year should NOT appear (only expired status triggers the banner)
        FiscalYear::factory()->for($this->org)->closed()->create([
            'name' => '2023',
            'start_date' => '2023-01-01',
            'end_date' => '2023-12-31',
        ]);

        $response = $this->actAsOrg()->get('/dashboard');

        $response->assertInertia(
            fn ($page) => $page
                ->component('Dashboard')
                ->where('expiredFiscalYear', null)
        );
    }
}
