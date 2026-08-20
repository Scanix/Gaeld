<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Actions\ReopenFiscalYearAction;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Exceptions\DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReopenFiscalYearActionTest extends TestCase
{
    use RefreshDatabase;

    private ReopenFiscalYearAction $action;

    private Organization $organization;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->action = app(ReopenFiscalYearAction::class);
        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Reopen Action Test Org',
            'currency' => 'CHF',
        ]);
    }

    public function test_reopens_year_by_fiscal_year_id_and_updates_fiscal_year_status(): void
    {
        $fiscalYear = FiscalYear::factory()->for($this->organization)->closed()->create([
            'start_date' => '2024-01-01',
            'end_date' => '2024-12-31',
        ]);
        $this->organization->closeFiscalYear(2024);

        $this->action->execute($this->organization, [
            'year' => 2024,
            'fiscal_year_id' => $fiscalYear->id,
        ], $this->user);

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2024));

        $fiscalYear->refresh();
        $this->assertSame(FiscalYearStatus::Expired, $fiscalYear->status);
    }

    public function test_falls_back_to_organization_closed_years_when_no_fiscal_year_record_matches(): void
    {
        $this->organization->closeFiscalYear(2023);

        $this->action->execute($this->organization, ['year' => 2023], $this->user);

        $this->organization->refresh();
        $this->assertFalse($this->organization->isFiscalYearClosed(2023));
    }

    public function test_throws_when_year_is_not_closed(): void
    {
        $this->expectException(DomainException::class);

        $this->action->execute($this->organization, ['year' => 2030], $this->user);
    }
}
