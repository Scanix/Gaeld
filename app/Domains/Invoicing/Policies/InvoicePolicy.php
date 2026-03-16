<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Users\Models\User;

class InvoicePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function view(User $user, Invoice $invoice): bool
    {
        return $user->organizations()->where('organizations.id', $invoice->organization_id)->exists();
    }

    public function create(User $user): bool
    {
        return $user->resolveCurrentOrganization() !== null;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status === Invoice::STATUS_DRAFT;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $this->view($user, $invoice) && $invoice->status === Invoice::STATUS_DRAFT;
    }
}
