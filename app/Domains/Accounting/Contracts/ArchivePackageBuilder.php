<?php

namespace App\Domains\Accounting\Contracts;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;

interface ArchivePackageBuilder
{
    public function request(Organization $organization, FiscalYear $year, ?User $actor = null, string $provenance = 'native', bool $archivalPdf = false): ArchivePackage;

    public function prepare(ArchivePackage $package, bool $archivalPdf = false): ArchivePackage;

    /** @return array{valid: bool, issues: list<string>} */
    public function verify(ArchivePackage $package): array;

    public function export(ArchivePackage $package): string;

    public function retry(ArchivePackage $package): ArchivePackage;
}
