<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\RecurringInvoice;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for recurring invoice template management.
 */
class RecurringInvoicePolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function view(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->belongsToOrganization($user, $recurringInvoice)
            && $user->hasPermissionTo(Permission::InvoicingView);
    }

    public function create(User $user): bool
    {
        return $this->hasCurrentOrganization($user)
            && $user->hasPermissionTo(Permission::InvoicingCreate);
    }

    public function update(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->belongsToOrganization($user, $recurringInvoice)
            && $user->hasPermissionTo(Permission::InvoicingEdit);
    }

    public function delete(User $user, RecurringInvoice $recurringInvoice): bool
    {
        return $this->belongsToOrganization($user, $recurringInvoice)
            && $user->hasPermissionTo(Permission::InvoicingDelete);
    }
}
