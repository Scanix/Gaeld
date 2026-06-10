<?php

namespace App\Domains\Accounting\Enums;

/**
 * Lifecycle status of a fiscal year.
 *
 * - Planned: pre-defined future period; not yet active for postings
 * - Operative: current active period accepting postings
 * - Expired: past period awaiting closing work; still open for adjustments
 * - Closed: frozen after year-end closing has been posted
 */
enum FiscalYearStatus: string
{
    case Planned = 'planned';
    case Operative = 'operative';
    case Expired = 'expired';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Planned => __('app.fiscal_year_status_planned'),
            self::Operative => __('app.fiscal_year_status_operative'),
            self::Expired => __('app.fiscal_year_status_expired'),
            self::Closed => __('app.fiscal_year_status_closed'),
        };
    }

    public function isClosed(): bool
    {
        return $this === self::Closed;
    }

    public function isOperative(): bool
    {
        return $this === self::Operative;
    }
}
