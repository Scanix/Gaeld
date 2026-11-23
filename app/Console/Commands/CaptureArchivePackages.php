<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Console\Command;

class CaptureArchivePackages extends Command
{
    protected $signature = 'archive:capture {--organization=} {--fiscal-year=} {--archival-pdf : Unsupported in R2; fails without creating a package} {--historical : Mark this as an explicit historical backfill}';

    protected $description = 'Capture consistent accounting evidence and enqueue preparation, deduplicating unchanged snapshots';

    public function handle(ArchivePackageService $packages): int
    {
        $failures = 0;
        $years = FiscalYear::withoutGlobalScopes()
            ->when($this->option('organization'), fn ($query, $id) => $query->where('organization_id', $id))
            ->when($this->option('fiscal-year'), fn ($query, $id) => $query->whereKey($id))
            ->whereDate('start_date', '<=', today());
        $years->orderBy('id')->chunkById(100, function ($years) use ($packages, &$failures): void {
            foreach ($years as $year) {
                try {
                    $organization = Organization::findOrFail($year->organization_id);
                    $package = $packages->request($organization, $year, provenance: $this->option('historical') ? 'historical_backfill' : 'native', archivalPdf: (bool) $this->option('archival-pdf') || (bool) config('archiving.proofs_enabled'));
                    $this->line($package->id.' '.$package->state.' v'.$package->version);
                } catch (\Throwable $exception) {
                    $failures++;
                    report($exception);
                    $this->error('Capture failed for fiscal year '.$year->id.': '.$exception->getMessage());
                }
            }
        });

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
