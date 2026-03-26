<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

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

    public function recordPayment(User $user, Invoice $invoice): bool
    {
        return $this->belongsToOrganization($user, $invoice)
            && $user->hasPermissionTo(Permission::InvoicingRecordPayment);
    }
}
