<?php

namespace Tests\Unit\Services;

use App\Domains\Payroll\Services\SwissDeductionService;
use Tests\TestCase;

class SwissDeductionServiceTest extends TestCase
{
    private SwissDeductionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SwissDeductionService;
    }

    public function test_calculate_deductions_with_default_rates(): void
    {
        $result = $this->service->calculateDeductions('10000.00');

        // AVS 5.3% of 10000 = 530.00
        $this->assertSame('530.00', $result['avs_employee']);
        $this->assertSame('530.00', $result['avs_employer']);

        // AC 1.1% of 10000 = 110.00
        $this->assertSame('110.00', $result['ac_employee']);
        $this->assertSame('110.00', $result['ac_employer']);

        // AANP 1.0% of 10000 = 100.00
        $this->assertSame('100.00', $result['aanp_employee']);

        // LPP 7.0% of 10000 = 700.00
        $this->assertSame('700.00', $result['lpp_employee']);
        $this->assertSame('700.00', $result['lpp_employer']);
    }

    public function test_total_employee_deductions(): void
    {
        $result = $this->service->calculateDeductions('10000.00');

        // Employee: AVS 530 + AC 110 + AANP 100 + LPP 700 = 1440.00
        $this->assertSame('1440.00', $result['total_employee']);
    }

    public function test_total_employer_deductions(): void
    {
        $result = $this->service->calculateDeductions('10000.00');

        // Employer: AVS 530 + AC 110 + LPP 700 = 1340.00
        $this->assertSame('1340.00', $result['total_employer']);
    }

    public function test_net_salary_calculation(): void
    {
        $result = $this->service->calculateDeductions('10000.00');

        // Net = 10000 - 1440 (employee total) = 8560.00
        $this->assertSame('8560.00', $result['net_salary']);
    }

    public function test_zero_salary(): void
    {
        $result = $this->service->calculateDeductions('0.00');

        $this->assertSame('0.00', $result['avs_employee']);
        $this->assertSame('0.00', $result['total_employee']);
        $this->assertSame('0.00', $result['total_employer']);
        $this->assertSame('0.00', $result['net_salary']);
    }

    public function test_deductions_with_cents(): void
    {
        $result = $this->service->calculateDeductions('5555.55');

        // AVS 5.3% of 5555.55 = 294.44 (rounded to 2 decimal places)
        $this->assertSame('294.44', $result['avs_employee']);
    }

    public function test_result_contains_all_expected_keys(): void
    {
        $result = $this->service->calculateDeductions('1000.00');

        $expectedKeys = [
            'avs_employee', 'avs_employer',
            'ac_employee', 'ac_employer',
            'aanp_employee',
            'lpp_employee', 'lpp_employer',
            'total_employee', 'total_employer',
            'net_salary',
        ];

        foreach ($expectedKeys as $key) {
            $this->assertArrayHasKey($key, $result, "Missing key: {$key}");
        }
    }

    public function test_empty_rates_collection_uses_defaults(): void
    {
        $result = $this->service->calculateDeductions('10000.00', collect());

        // Should use defaults — same as no rates
        $this->assertSame('530.00', $result['avs_employee']);
        $this->assertSame('8560.00', $result['net_salary']);
    }
}
