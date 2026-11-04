<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchivePackageService;
use App\Domains\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Throwable;

#[Signature('archive:check {--organization= : Limit to one organization UUID} {--annual : Produce an annual completeness report} {--recovery-report= : JSON report from a separate recovery environment}')]
#[Description('Check local package content and record a control report without asserting archival proofs')]
class ArchiveCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ArchivePackageService $packagesService, ArchiveAccessLog $audit): int
    {
        $organizations = Organization::withTrashed()->when($this->option('organization'), fn ($q) => $q->whereKey($this->option('organization')))->get();
        if ($organizations->isEmpty()) {
            $this->error('No matching organization.');

            return self::FAILURE;
        }
        $recovery = null;
        if ($path = $this->option('recovery-report')) {
            try {
                if (! is_file($path) || filesize($path) > 1048576) {
                    throw new \RuntimeException('Recovery report must be an existing JSON file under 1 MiB.');
                }
                $bytes = file_get_contents($path);
                if ($bytes === false) {
                    throw new \RuntimeException('The independent recovery report could not be read.');
                }
                $recovery = json_decode($bytes, true, 32, JSON_THROW_ON_ERROR);
                if (! is_array($recovery) || ! is_string($recovery['environment'] ?? null) || trim($recovery['environment']) === '' || $recovery['environment'] === gethostname()
                    || ! is_string($recovery['operator'] ?? null) || trim($recovery['operator']) === ''
                    || ! is_string($recovery['completed_at'] ?? null) || ! is_array($recovery['packages'] ?? null) || empty($recovery['packages'])) {
                    throw new \RuntimeException('Recovery report needs a distinct environment, operator, completion date and recovered package hashes.');
                }
                if (Carbon::parse($recovery['completed_at'])->lt(now()->subYear()) || Carbon::parse($recovery['completed_at'])->isFuture()) {
                    throw new \RuntimeException('Recovery report must describe a completed recovery within the past year.');
                }
            } catch (Throwable $exception) {
                $this->error($exception->getMessage());

                return self::FAILURE;
            }
        }
        $failed = false;
        foreach ($organizations as $organization) {
            $packages = ArchivePackage::withoutGlobalScopes()->where('organization_id', $organization->id)->get();
            $checks = [];
            $issues = [];
            $uncapturedYears = FiscalYear::withoutGlobalScopes()->where('organization_id', $organization->id)
                ->whereDate('start_date', '<=', today())->whereNotIn('id', $packages->pluck('fiscal_year_id'))->pluck('id');
            foreach ($uncapturedYears as $yearId) {
                $issues[] = 'Financial year has no preservation package: '.$yearId;
            }
            foreach ($packages as $package) {
                if (! $package->manifest_sha256) {
                    $issues[] = 'Unprepared package: '.$package->id;

                    continue;
                }
                try {
                    $checks[$package->id] = $packagesService->verify($package);
                } catch (Throwable $exception) {
                    $checks[$package->id] = ['valid' => false, 'issues' => [$exception->getMessage()]];
                }
                if (! $checks[$package->id]['valid']) {
                    $issues[] = 'Failed check: '.$package->id;
                }
            }
            if ($this->option('annual')) {
                foreach ($packages as $package) {
                    if (! $recovery || ($recovery['packages'][$package->id] ?? null) !== $package->manifest_sha256) {
                        $issues[] = 'Independent recovery evidence missing: '.$package->id;
                    }
                }
                if ($packages->isEmpty() || ! $recovery) {
                    $issues[] = 'An annual control requires an independent recovery drill.';
                }
            }
            $organizationRecovery = $recovery === null ? null : [
                'environment' => $recovery['environment'],
                'operator' => $recovery['operator'],
                'completed_at' => $recovery['completed_at'],
                'packages' => array_intersect_key($recovery['packages'], array_fill_keys($packages->modelKeys(), true)),
            ];
            $report = [
                'report_id' => (string) Str::uuid(), 'type' => $this->option('annual') ? 'annual' : 'daily',
                'organization_id' => $organization->id, 'completed_at' => now()->toIso8601String(),
                'scope' => 'local-catalog-integrity-and-recovery-attestation', 'proofs_checked' => false, 'valid' => $issues === [], 'checks' => $checks, 'issues' => $issues,
                'recovery_attestation' => $organizationRecovery, 'recovery_attestation_is_operator_supplied' => $recovery !== null,
            ];
            $audit->record($organization->id, null, 'control.'.$report['type'], null, $report);
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failed = $failed || $issues !== [];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
