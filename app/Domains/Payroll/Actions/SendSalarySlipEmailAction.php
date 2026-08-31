<?php

namespace App\Domains\Payroll\Actions;

use App\Domains\Payroll\Jobs\SendSalarySlipEmailJob;
use App\Domains\Payroll\Models\SalarySlip;

class SendSalarySlipEmailAction
{
    public function execute(SalarySlip $slip): void
    {
        $slip->loadMissing('employee');

        if ($slip->email_sent_at !== null || ! $slip->employee->email) {
            return;
        }

        SendSalarySlipEmailJob::dispatch(
            (string) $slip->getKey(),
            (string) $slip->organization_id,
        )->afterCommit();
    }
}
