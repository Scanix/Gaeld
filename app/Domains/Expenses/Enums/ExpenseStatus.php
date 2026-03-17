<?php

namespace App\Domains\Expenses\Enums;

enum ExpenseStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Posted = 'posted';

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

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
            self::Pending => 'Pending',
            self::Approved => 'Approved',
            self::Posted => 'Posted',
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
