<?php

namespace App\Support\Contracts;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;

interface OrganizationQuotaResolver
{
    public function maxUsers(Organization $organization): int;

    public function maxOrganizations(User $user): int;

    public function maxInvoicesPerMonth(Organization $organization): int;

    public function maxOcrScansPerDay(Organization $organization): int;

    public function maxOcrScansPerMonth(Organization $organization): int;

    public function maxStorageBytes(Organization $organization): int;
}
