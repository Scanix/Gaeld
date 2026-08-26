<?php

namespace App\Domains\Payroll\Controllers\Concerns;

use App\Domains\Organizations\Models\Organization;
use App\Support\FeatureFlag;

trait EnsuresPayrollWritable
{
    private function ensurePayrollWritable(Organization $organization): void
    {
        abort_unless(
            FeatureFlag::enabledForOrg('payroll', $organization),
            403,
            __('app.payroll_plan_upgrade_required'),
        );
    }
}
