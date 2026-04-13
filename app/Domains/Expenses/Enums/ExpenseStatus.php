<?php

namespace App\Domains\Expenses\Enums;

/** Expense lifecycle status: pending → approved → posted (or rejected). */
enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Posted = 'posted';

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
            self::Pending => [self::Approved],
            self::Approved => [self::Posted],
            self::Posted => [],
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('app.expense_status_pending'),
            self::Approved => __('app.expense_status_approved'),
            self::Posted => __('app.expense_status_posted'),
        };
    }

    public function isEditable(): bool
    {
        return $this !== self::Posted;
    }

    public function isDeletable(): bool
    {
        return $this === self::Pending;
    }
}
