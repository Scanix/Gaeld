<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists()
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null
            && $user->hasPermissionTo(Permission::InvoicingCreate);
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists()
            && $user->hasPermissionTo(Permission::InvoicingEdit)
            && $invoice->status->isEditable();
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists()
            && $user->hasPermissionTo(Permission::InvoicingDelete)
            && $invoice->status->isDeletable();
    }

    public function finalize(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists()
            && $user->hasPermissionTo(Permission::InvoicingFinalize);
    }

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists()
            && $user->hasPermissionTo(Permission::InvoicingRecordPayment);
    }
}
