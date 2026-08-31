<?php

namespace App\Domains\Payroll\Jobs;

use App\Domains\Payroll\Mail\SalarySlipReadyMail;
use App\Domains\Payroll\Models\Employee;
use App\Domains\Payroll\Models\SalarySlip;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Mail;

class SendSalarySlipEmailJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300, 900];

    public int $uniqueFor = 86400;

    public function __construct(
        public readonly string $salarySlipId,
        public readonly string $organizationId,
    ) {}

    public function uniqueId(): string
    {
        return $this->salarySlipId;
    }

    public function handle(): void
    {
        $slip = SalarySlip::withoutGlobalScopes()
            ->with('organization')
            ->where('organization_id', $this->organizationId)
            ->findOrFail($this->salarySlipId);

        if ($slip->email_sent_at !== null || ! $slip->isPosted()) {
            return;
        }

        $employeeData = $slip->employee_snapshot;
        $recipient = is_array($employeeData) && isset($employeeData['email'])
            ? (string) $employeeData['email']
            : null;
        if (! $recipient) {
            $slip->loadMissing('employee');
            $employee = $slip->getRelation('employee');
            $recipient = $employee instanceof Employee ? $employee->email : null;
        }

        if (! $recipient) {
            return;
        }

        Mail::to($recipient)
            ->locale($slip->organization->locale ?? app()->getLocale())
            ->send(new SalarySlipReadyMail($slip));

        $slip->forceFill(['email_sent_at' => now()])->saveQuietly();
    }
}
