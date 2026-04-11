<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerQueryService;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Payroll\Models\SalarySlip;
use App\Support\Money;
use Carbon\Carbon;

/**
 * Posts a salary slip to the accounting ledger (gross salary, deductions, net pay).
 */
class PostPayrollAction
{
    public function __construct(
        private LedgerService $ledger,
        private LedgerQueryService $ledgerQuery,
    ) {}

    public function execute(SalarySlip $slip): SalarySlip
    {
        $deductions = $slip->deductions;
        $orgId = $slip->organization_id;

        $employee = $slip->employee;
        $description = "Salary {$employee->fullName()} — {$slip->period_month}/{$slip->period_year}";

        // Resolve accounts
        $salaryAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::SALARIES);
        $socialChargesAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::SOCIAL_CHARGES_EMPLOYER);
        $bankAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::BANK_CASH);
        $avsAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::AVS_PAYABLE);
        $acAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::AC_PAYABLE);
        $lppAccount = $this->ledgerQuery->resolveAccount($orgId, AccountCode::LPP_PAYABLE);

        // Calculate aggregated amounts for liability accounts
        $avsTotal = Money::add(
            $deductions['avs_employee'] ?? '0',
            $deductions['avs_employer'] ?? '0',
        );
        // Include AANP in AVS payable if present
        $avsTotal = Money::add($avsTotal, $deductions['aanp_employee'] ?? '0');

        $acTotal = Money::add(
            $deductions['ac_employee'] ?? '0',
            $deductions['ac_employer'] ?? '0',
        );

        $lppTotal = Money::add(
            $deductions['lpp_employee'] ?? '0',
            $deductions['lpp_employer'] ?? '0',
        );

        $lines = [];

        // Debit: Gross salary
        $lines[] = new JournalLineData(
            accountId: (string) $salaryAccount->id,
            debit: $slip->gross_salary,
            credit: '0',
            description: "Gross salary: {$employee->fullName()}",
        );

        // Debit: Employer social charges
        $totalEmployer = $deductions['total_employer'] ?? '0';
        if (bccomp($totalEmployer, '0', 2) > 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $socialChargesAccount->id,
                debit: $totalEmployer,
                credit: '0',
                description: "Employer social charges: {$employee->fullName()}",
            );
        }

        // Credit: Bank (net salary)
        $lines[] = new JournalLineData(
            accountId: (string) $bankAccount->id,
            debit: '0',
            credit: $slip->net_salary,
            description: "Net salary paid: {$employee->fullName()}",
        );

        // Credit: AVS/AI/APG payable
        if (bccomp($avsTotal, '0', 2) > 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $avsAccount->id,
                debit: '0',
                credit: $avsTotal,
                description: 'AVS/AI/APG contributions',
            );
        }

        // Credit: AC payable
        if (bccomp($acTotal, '0', 2) > 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $acAccount->id,
                debit: '0',
                credit: $acTotal,
                description: 'Unemployment insurance (AC)',
            );
        }

        // Credit: LPP payable
        if (bccomp($lppTotal, '0', 2) > 0) {
            $lines[] = new JournalLineData(
                accountId: (string) $lppAccount->id,
                debit: '0',
                credit: $lppTotal,
                description: 'Pension fund (LPP)',
            );
        }

        $entry = new JournalEntryData(
            date: Carbon::create($slip->period_year, $slip->period_month)->endOfMonth()->toDateString(),
            reference: "PAY-{$slip->employee_id}-{$slip->period_year}-{$slip->period_month}",
            description: $description,
            lines: $lines,
        );

        $journalEntry = $this->ledger->postEntry($orgId, $entry);

        $slip->update([
            'journal_entry_id' => $journalEntry->id,
            'posted_at' => now(),
        ]);

        return $slip->fresh();
    }
}
