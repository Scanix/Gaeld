<?php

namespace App\Domains\Invoicing\Enums;

use Carbon\Carbon;

/** Frequency at which a recurring invoice is generated. */
enum RecurrenceFrequency: string
{
    case Weekly = 'weekly';
    case Monthly = 'monthly';
    case Quarterly = 'quarterly';
    case Yearly = 'yearly';

    public function nextDate(Carbon $from): Carbon
    {
        return match ($this) {
            self::Weekly => $from->copy()->addWeek(),
            self::Monthly => $from->copy()->addMonth(),
            self::Quarterly => $from->copy()->addMonths(3),
            self::Yearly => $from->copy()->addYear(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Weekly => __('app.recurrence_frequency_weekly'),
            self::Monthly => __('app.recurrence_frequency_monthly'),
            self::Quarterly => __('app.recurrence_frequency_quarterly'),
            self::Yearly => __('app.recurrence_frequency_yearly'),
        };
    }
}
