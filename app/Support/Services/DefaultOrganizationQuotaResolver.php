<?php

namespace App\Support\Services;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use App\Support\Contracts\OrganizationQuotaResolver;

final class DefaultOrganizationQuotaResolver implements OrganizationQuotaResolver
{
    public function maxUsers(Organization $organization): int
    {
        return -1;
    }

    public function maxOrganizations(User $user): int
    {
        return (int) config('features.max_organizations_per_user', 10);
    }

    public function maxInvoicesPerMonth(Organization $organization): int
    {
        return -1;
    }

    public function maxOcrScansPerDay(Organization $organization): int
    {
        return (int) config('services.ocr.daily_limit', 3);
    }

    public function maxOcrScansPerMonth(Organization $organization): int
    {
        return -1;
    }

    public function maxStorageBytes(Organization $organization): int
    {
        return -1;
    }
}
