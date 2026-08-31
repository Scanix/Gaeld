<?php

namespace App\Domains\Invoicing\Policies;

use App\Domains\Invoicing\Models\InvoiceCatalogItem;
use App\Domains\Users\Models\User;
use App\Support\Policies\BasePolicy;

/**
 * Authorization policy for invoice catalog items.
 *
 * Catalog items are read by any organization member (needed to populate the
 * item selector when creating an invoice); only organization editors may
 * create/delete them, matching InvoiceCatalogItemController's existing
 * store()/destroy() checks against Organization::update.
 */
class InvoiceCatalogItemPolicy extends BasePolicy
{
    public function viewAny(User $user): bool
    {
        return $this->hasCurrentOrganization($user);
    }

    public function view(User $user, InvoiceCatalogItem $catalogItem): bool
    {
        return $this->belongsToOrganization($user, $catalogItem);
    }
}
