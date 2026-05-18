<?php

namespace App\Domains\Accounting\Notifications;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class FiscalYearExpiredNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        private readonly FiscalYear $fiscalYear,
    ) {}

    /** @return string[] */
    public function via(User $notifiable): array
    {
        if (! $notifiable->wantsEmailNotification('fiscal_year_expired')) {
            return [];
        }

        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toDatabase(User $notifiable): array
    {
        return [
            'type' => 'fiscal_year_expired',
            'fiscal_year_id' => $this->fiscalYear->id,
            'year' => $this->fiscalYear->name,
            'end_date' => $this->fiscalYear->end_date->toDateString(),
            'url' => route('accounting.closing', ['fiscal_year_id' => $this->fiscalYear->id]),
        ];
    }
}
