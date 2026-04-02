<?php

namespace App\Domains\Api\Enums;

/** Webhook event names dispatched by the application (e.g. invoice.created). */
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

    public function label(): string
    {
        return match ($this) {
            self::InvoiceCreated => __('app.webhook_event_invoice_created'),
            self::InvoiceUpdated => __('app.webhook_event_invoice_updated'),
            self::InvoiceDeleted => __('app.webhook_event_invoice_deleted'),
            self::InvoiceFinalized => __('app.webhook_event_invoice_finalized'),
            self::InvoicePaymentRecorded => __('app.webhook_event_invoice_payment_recorded'),
            self::CustomerCreated => __('app.webhook_event_customer_created'),
            self::CustomerUpdated => __('app.webhook_event_customer_updated'),
            self::CustomerDeleted => __('app.webhook_event_customer_deleted'),
            self::ExpenseCreated => __('app.webhook_event_expense_created'),
            self::ExpenseUpdated => __('app.webhook_event_expense_updated'),
            self::ExpenseDeleted => __('app.webhook_event_expense_deleted'),
            self::ExpenseApproved => __('app.webhook_event_expense_approved'),
        };
    }
}
