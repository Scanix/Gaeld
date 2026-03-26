<?php

namespace App\Domains\Api\Enums;

enum WebhookEvent: string
{
    case InvoiceCreated = 'invoice.created';
    case InvoiceUpdated = 'invoice.updated';
    case InvoiceDeleted = 'invoice.deleted';
    case InvoiceFinalized = 'invoice.finalized';
    case InvoicePaymentRecorded = 'invoice.payment_recorded';

    case CustomerCreated = 'customer.created';
    case CustomerUpdated = 'customer.updated';
    case CustomerDeleted = 'customer.deleted';

    case ExpenseCreated = 'expense.created';
    case ExpenseUpdated = 'expense.updated';
    case ExpenseDeleted = 'expense.deleted';
    case ExpenseApproved = 'expense.approved';

    public static function all(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function isValid(string $event): bool
    {
        return self::tryFrom($event) !== null;
    }
}
