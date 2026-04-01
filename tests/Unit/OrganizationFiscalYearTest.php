<?php

namespace Tests\Unit;

use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationFiscalYearTest extends TestCase
{
    use RefreshDatabase;

    public function test_reopen_fiscal_year_removes_year_from_closed_list(): void
    {
        $org = Organization::create(['name' => 'Test', 'currency' => 'CHF']);
        $org->closeFiscalYear(2024);
        $org->closeFiscalYear(2025);
        $this->assertTrue($org->isFiscalYearClosed(2024));
        $this->assertTrue($org->isFiscalYearClosed(2025));

        $org->reopenFiscalYear(2024);

        $org->refresh();
        $this->assertFalse($org->isFiscalYearClosed(2024));
        $this->assertTrue($org->isFiscalYearClosed(2025));
    }

    public function test_reopen_fiscal_year_when_not_closed_is_safe(): void
    {
        $org = Organization::create(['name' => 'Test', 'currency' => 'CHF']);
        $org->closeFiscalYear(2025);

        $org->reopenFiscalYear(2023); // not in list

        $org->refresh();
        $this->assertTrue($org->isFiscalYearClosed(2025));
        $this->assertFalse($org->isFiscalYearClosed(2023));
    }

    public function test_reopen_last_closed_year_results_in_empty_array(): void
    {
        $org = Organization::create(['name' => 'Test', 'currency' => 'CHF']);
        $org->closeFiscalYear(2025);

        $org->reopenFiscalYear(2025);

        $org->refresh();
        $this->assertSame([], $org->closed_fiscal_years);
    }
}
