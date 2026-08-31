<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Payroll\Models\SalarySlip;

/**
 * Reverts a posted salary slip back to draft so it can be corrected (fixed
 * gross salary, deductions, etc.) or deleted and regenerated. Reverses the
 * slip's journal entry the same way invoice/expense corrections do elsewhere
 * in the app.
 */
class UnpostPayrollAction
{
    public function __construct(
        private LedgerService $ledger,
    ) {}

    public function execute(SalarySlip $slip): SalarySlip
    {
        if (! $slip->isPosted()) {
            throw new \DomainException('Only a posted salary slip can be unposted.');
        }

        if ($slip->journal_entry_id) {
            $slip->loadMissing('journalEntry.lines');
            $reversal = $this->ledger->reverseEntry(
                $slip->journalEntry,
                "Unposting salary slip for {$slip->period_month}/{$slip->period_year}",
            );
            $this->ledger->postDraft($reversal);
        }

        $slip->update([
            'journal_entry_id' => null,
            'posted_at' => null,
        ]);

        return $slip->fresh();
    }
}
