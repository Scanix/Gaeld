<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Accounting\Constants\AccountCode;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Payroll\Models\SalarySlip;

class PostPayrollAction
{
    public function __construct(
        private LedgerService $ledger,
    ) {}

    public function execute(SalarySlip $slip): SalarySlip
    {
        $deductions = $slip->deductions;
        $orgId = $slip->organization_id;

        $employee = $slip->employee;
        $description = "Salary {$employee->fullName()} — {$slip->period_month}/{$slip->period_year}";

        // Resolve accounts
        $salaryAccount = $this->ledger->resolveAccount($orgId, AccountCode::SALARIES);
        $socialChargesAccount = $this->ledger->resolveAccount($orgId, AccountCode::SOCIAL_CHARGES_EMPLOYER);
        $bankAccount = $this->ledger->resolveAccount($orgId, AccountCode::BANK_CASH);
        $avsAccount = $this->ledger->resolveAccount($orgId, AccountCode::AVS_PAYABLE);
        $acAccount = $this->ledger->resolveAccount($orgId, AccountCode::AC_PAYABLE);
        $lppAccount = $this->ledger->resolveAccount($orgId, AccountCode::LPP_PAYABLE);

        // Calculate aggregated amounts for liability accounts
        $avsTotal = bcadd(
            $deductions['avs_employee'] ?? '0',
            $deductions['avs_employer'] ?? '0',
            2,
        );
        // Include AANP in AVS payable if present
        $avsTotal = bcadd($avsTotal, $deductions['aanp_employee'] ?? '0', 2);

        $acTotal = bcadd(
            $deductions['ac_employee'] ?? '0',
            $deductions['ac_employer'] ?? '0',
            2,
        );

        $lppTotal = bcadd(
            $deductions['lpp_employee'] ?? '0',
            $deductions['lpp_employer'] ?? '0',
            2,
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
            date: now()->toDateString(),
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
