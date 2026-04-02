<?php

namespace App\Domains\Migration\Enums;

/**
 * Status of a migration session or individual data type import.
 */
enum ImportStatus: string
{
    case Pending = 'pending';
    case Validating = 'validating';
    case Importing = 'importing';
    case Completed = 'completed';
    case Failed = 'failed';
    case PartiallyCompleted = 'partially_completed';
    case Reversing = 'reversing';
    case Reversed = 'reversed';

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public function label(): string
    {
        return match ($this) {
            self::Pending => __('app.import_status_pending'),
            self::Validating => __('app.import_status_validating'),
            self::Importing => __('app.import_status_importing'),
            self::Completed => __('app.import_status_completed'),
            self::Failed => __('app.import_status_failed'),
            self::PartiallyCompleted => __('app.import_status_partially_completed'),
            self::Reversing => __('app.import_status_reversing'),
            self::Reversed => __('app.import_status_reversed'),
        };
    }

    public function canTransitionTo(self $target): bool
    {
        return in_array($target, $this->allowedTransitions(), true);
    }

    /**
     * @return self[]
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Validating, self::Failed],
            self::Validating => [self::Importing, self::Failed],
            self::Importing => [self::Completed, self::PartiallyCompleted, self::Failed],
            self::Completed => [self::Reversing],
            self::PartiallyCompleted => [self::Reversing],
            self::Failed => [self::Pending],
            self::Reversing => [self::Reversed, self::Failed],
            self::Reversed => [],
        };
    }

    public function isTerminal(): bool
    {
        return $this === self::Reversed;
    }
}
