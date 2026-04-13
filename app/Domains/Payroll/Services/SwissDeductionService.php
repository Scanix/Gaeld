<?php

namespace App\Domains\Payroll\Services;

use App\Domains\Payroll\Models\DeductionRate;
use App\Support\Money;
use Illuminate\Support\Collection;
use Psr\Log\LoggerInterface;

/**
 * Provides Swiss payroll deduction rates (AVS, AC, LPP, AANP, etc.)
 * and calculates employee/employer deduction amounts from gross salary.
 *
 * Falls back to built-in 2026 rate defaults when the organization
 * has not configured custom deduction rates.
 */
class SwissDeductionService
{
    /**
     * Default Swiss deduction rates (2026 estimates) used when
     * the organization has not configured custom rates.
     */
    private const DEFAULTS = [
        ['code' => 'avs_employee', 'name' => 'AVS/AI/APG (employee)', 'rate' => '5.3000', 'type' => 'employee'],
        ['code' => 'avs_employer', 'name' => 'AVS/AI/APG (employer)', 'rate' => '5.3000', 'type' => 'employer'],
        ['code' => 'ac_employee', 'name' => 'AC (employee)', 'rate' => '1.1000', 'type' => 'employee'],
        ['code' => 'ac_employer', 'name' => 'AC (employer)', 'rate' => '1.1000', 'type' => 'employer'],
        ['code' => 'aanp_employee', 'name' => 'AANP (employee)', 'rate' => '1.0000', 'type' => 'employee'],
        ['code' => 'lpp_employee', 'name' => 'LPP (employee)', 'rate' => '7.0000', 'type' => 'employee'],
        ['code' => 'lpp_employer', 'name' => 'LPP (employer)', 'rate' => '7.0000', 'type' => 'employer'],
    ];

    // ──────────────────────────────────────────────────────────────
    //  Calculation
    // ──────────────────────────────────────────────────────────────

    /**
     * Calculate all deductions for a given gross salary.
     *
     * @param  Collection<int, DeductionRate>|null  $rates
     * @return array{avs_employee: string, avs_employer: string, ac_employee: string, ac_employer: string, aanp_employee: string, lpp_employee: string, lpp_employer: string, total_employee: string, total_employer: string, net_salary: string}
     */
    public function calculateDeductions(string $grossSalary, ?Collection $rates = null): array
    {
        if ($rates === null || $rates->isEmpty()) {
            try {
                app(LoggerInterface::class)->warning(
                    'SwissDeductionService: no custom deduction rates found — falling back to built-in defaults. '
                    .'Verify your organisation\'s deduction rates are up to date for the current fiscal year.',
                );
            } catch (\Throwable) {
                // Logger not available in pure unit-test context — silently skip.
            }
        }

        $rateMap = $this->buildRateMap($rates);

        $deductions = [];
        $totalEmployee = '0.00';
        $totalEmployer = '0.00';

        foreach ($rateMap as $code => $rate) {
            $amount = Money::percentage($grossSalary, $rate['rate']);
            $deductions[$code] = $amount;

            if ($rate['type'] === 'employee') {
                $totalEmployee = Money::add($totalEmployee, $amount);
            } else {
                $totalEmployer = Money::add($totalEmployer, $amount);
            }
        }

        $deductions['total_employee'] = $totalEmployee;
        $deductions['total_employer'] = $totalEmployer;
        $deductions['net_salary'] = Money::subtract($grossSalary, $totalEmployee);

        return $deductions;
    }

    // ──────────────────────────────────────────────────────────────
    //  Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Build the rate map from custom rates or defaults.
     *
     * @param  Collection<int, DeductionRate>|null  $rates
     * @return array<string, array{code: string, name: string, rate: string, type: string}>
     */
    private function buildRateMap(?Collection $rates): array
    {
        if ($rates === null || $rates->isEmpty()) {
            return collect(self::DEFAULTS)->keyBy('code')->toArray();
        }

        return $rates
            ->where('is_active', true)
            ->keyBy('code')
            ->map(fn (DeductionRate $rate) => [
                'code' => $rate->code,
                'name' => $rate->name,
                'rate' => $rate->rate,
                'type' => $rate->type,
            ])
            ->toArray();
    }
}
