<?php

namespace App\Domains\Invoicing\Enums;

/** Invoice lifecycle status: draft → sent → paid / overdue / cancelled. */
enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Sent = 'sent';
    case Paid = 'paid';
    case Overdue = 'overdue';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Draft => [self::Sent, self::Cancelled],
            self::Sent => [self::Paid, self::Overdue, self::Cancelled],
            self::Overdue => [self::Paid, self::Cancelled],
            self::Paid, self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Draft => __('app.invoice_status_draft'),
            self::Sent => __('app.invoice_status_sent'),
            self::Paid => __('app.invoice_status_paid'),
            self::Overdue => __('app.invoice_status_overdue'),
            self::Cancelled => __('app.invoice_status_cancelled'),
        };
    }

    public function isEditable(): bool
    {
        return $this === self::Draft;
    }

    public function isDeletable(): bool
    {
        return in_array($this, [self::Draft, self::Cancelled], true);
    }
}
