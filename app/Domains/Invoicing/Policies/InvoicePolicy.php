<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for invoice lifecycle operations including status transitions.
 */
class InvoicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::InvoicingCreate);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingEdit)
            && $invoice->status->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingDelete)
            && $invoice->status->isDeletable();
    }

    public function finalize(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingFinalize);
    }

    public function duplicate(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingCreate);
    }

    public function creditNote(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingCreate);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingRecordPayment)
            && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true);
    }

    public function send(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingEdit)
            && in_array($invoice->status, [InvoiceStatus::Sent, InvoiceStatus::Overdue], true);
    }

    public function cancel(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingEdit)
            && $invoice->status->canTransitionTo(InvoiceStatus::Cancelled);
    }

    public function forceDelete(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingDelete)
            && $user->hasRole('owner')
            && ($invoice->trashed() || $invoice->status === InvoiceStatus::Cancelled);
    }
}
