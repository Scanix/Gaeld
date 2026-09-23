<?php

namespace App\Domains\Expenses\Enums;

/** Expense lifecycle status: pending → approved → posted → cancelled. */
enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Posted = 'posted';
    case Cancelled = 'cancelled';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return array<int, self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Approved, self::Posted],
            self::Approved => [self::Posted, self::Pending],
            self::Posted => [self::Cancelled],
            self::Cancelled => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('app.expense_status_pending'),
            self::Approved => __('app.expense_status_approved'),
            self::Posted => __('app.expense_status_posted'),
            self::Cancelled => __('app.expense_status_cancelled'),
        };
    }

    public function isEditable(): bool
    {
        return in_array($this, [self::Pending, self::Approved], true);
    }

    public function isDeletable(): bool
    {
        return $this === self::Pending || $this === self::Approved;
    }
}
