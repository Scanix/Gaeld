<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Services\LegalArchivingService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Console\Command;

class ArchiveFiscalYearCommand extends Command
{
    protected $signature = 'archive:fiscal-year {org : Organization UUID} {year : Fiscal year (YYYY)}';

    protected $description = 'Archive all documents for a closed fiscal year (Swiss OR Art. 958f — 10-year retention)';

    public function handle(LegalArchivingService $service): int
    {
        $orgId = $this->argument('org');
        $year = (int) $this->argument('year');

        $org = Organization::find($orgId);
        if ($org === null) {
            $this->error("Organization {$orgId} not found.");

            return self::FAILURE;
        }

        $this->info("Archiving fiscal year {$year} for organization: {$org->name}");

        $service->archiveFiscalYear($orgId, $year);

        $this->info('Archiving complete.');

        return self::SUCCESS;
    }
}
