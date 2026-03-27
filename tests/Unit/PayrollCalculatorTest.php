<?php

namespace Tests\Unit;

use App\Domains\Payroll\Services\SwissDeductionService;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PayrollCalculatorTest extends TestCase
{
    private SwissDeductionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SwissDeductionService;
    }

    #[Test]
    public function calculates_correct_deductions_for_6000_gross(): void
    {
        $deductions = $this->service->calculateDeductions('6000.00');

        // AVS employee: 6000 * 5.3% = 318.00
        $this->assertSame('318.00', $deductions['avs_employee']);

        // AVS employer: 6000 * 5.3% = 318.00
        $this->assertSame('318.00', $deductions['avs_employer']);

        // AC employee: 6000 * 1.1% = 66.00
        $this->assertSame('66.00', $deductions['ac_employee']);

        // AC employer: 6000 * 1.1% = 66.00
        $this->assertSame('66.00', $deductions['ac_employer']);

        // AANP employee: 6000 * 1.0% = 60.00
        $this->assertSame('60.00', $deductions['aanp_employee']);

        // LPP employee: 6000 * 7.0% = 420.00
        $this->assertSame('420.00', $deductions['lpp_employee']);

        // LPP employer: 6000 * 7.0% = 420.00
        $this->assertSame('420.00', $deductions['lpp_employer']);

        // Total employee: 318 + 66 + 60 + 420 = 864
        $this->assertSame('864.00', $deductions['total_employee']);

        // Total employer: 318 + 66 + 420 = 804
        $this->assertSame('804.00', $deductions['total_employer']);

        // Net salary: 6000 - 864 = 5136
        $this->assertSame('5136.00', $deductions['net_salary']);
    }

    #[Test]
    public function calculates_correct_net_salary(): void
    {
        $deductions = $this->service->calculateDeductions('10000.00');

        // Total employee deductions: 10000 * (5.3 + 1.1 + 1.0 + 7.0)% = 10000 * 14.4% = 1440
        $this->assertSame('1440.00', $deductions['total_employee']);
        $this->assertSame('8560.00', $deductions['net_salary']);
    }

    #[Test]
    public function handles_zero_gross(): void
    {
        $deductions = $this->service->calculateDeductions('0.00');

        $this->assertSame('0.00', $deductions['total_employee']);
        $this->assertSame('0.00', $deductions['total_employer']);
        $this->assertSame('0.00', $deductions['net_salary']);
    }

    #[Test]
    public function employer_total_excludes_aanp(): void
    {
        // AANP is employee-only in default rates
        $deductions = $this->service->calculateDeductions('5000.00');

        // Employer: AVS 265 + AC 55 + LPP 350 = 670
        $this->assertSame('670.00', $deductions['total_employer']);
    }
}
