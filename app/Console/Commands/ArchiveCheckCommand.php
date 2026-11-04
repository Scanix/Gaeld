<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use App\Domains\Accounting\Services\ArchiveProofService;
use App\Domains\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

#[Signature('archive:check {--organization= : Limit to one organization UUID} {--annual : Produce an annual completeness report} {--recovery-report= : JSON report from a separate recovery environment}')]
#[Description('Check preserved files, proofs and protected inventories; retain a control report')]
class ArchiveCheckCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ArchiveProofService $proofs, ProtectedArchiveStorage $storage, ArchiveAccessLog $audit): int
    {
        $organizations = Organization::withTrashed()->when($this->option('organization'), fn ($q) => $q->whereKey($this->option('organization')))->get();
        $protectedInventory = null;
        $globalIssues = [];
        $hasProtectedStorage = config('archiving.proofs_enabled') || config('archiving.s3.bucket')
            || ArchivePackage::withoutGlobalScopes()->whereNotNull('proof_result')->exists();
        if (! $this->option('organization') && $hasProtectedStorage) {
            try {
                $protectedInventory = $storage->inventory();
                $localIds = $organizations->pluck('id')->all();
                foreach ($protectedInventory as $entry) {
                    if (! in_array($entry['organization_id'], $localIds, true)) {
                        $globalIssues[] = 'Organization missing from local catalog: '.$entry['organization_id'];
                    }
                }
                $globalIssues = array_values(array_unique($globalIssues));
            } catch (Throwable $exception) {
                $globalIssues[] = 'Global protected inventory could not be checked: '.$exception->getMessage();
            }
            $globalReport = [
                'report_id' => (string) Str::uuid(), 'type' => 'global-catalog', 'completed_at' => now()->toIso8601String(),
                'valid' => $globalIssues === [], 'issues' => $globalIssues,
                'local_organizations' => $organizations->pluck('id')->all(),
                'protected_organizations' => $protectedInventory === null ? null : array_values(array_unique(array_column($protectedInventory, 'organization_id'))),
            ];
            $reportBytes = json_encode($globalReport, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (! Storage::disk('local')->put('preservation-controls/global/'.$globalReport['report_id'].'.json', $reportBytes)) {
                $this->error('The global completeness report could not be retained.');

                return self::FAILURE;
            }
            $this->line($reportBytes);
        }
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
        $failed = $globalIssues !== [];
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
                    $checks[$package->id] = $proofs->check($package);
                } catch (Throwable $exception) {
                    $checks[$package->id] = ['valid' => false, 'issues' => [$exception->getMessage()]];
                }
                if (! $checks[$package->id]['valid']) {
                    $issues[] = 'Failed check: '.$package->id;
                }
            }
            if ($hasProtectedStorage) {
                try {
                    $inventory = $protectedInventory === null
                        ? $storage->inventory($organization->id)
                        : array_values(array_filter($protectedInventory, fn (array $entry): bool => $entry['organization_id'] === $organization->id));
                    foreach ($inventory as $entry) {
                        $known = $packages->firstWhere('id', $entry['package_id']);
                        if (! $known || $known->manifest_sha256 !== $entry['manifest_sha256']) {
                            $issues[] = 'Protected inventory differs from local catalog: '.$entry['package_id'];
                        }
                    }
                    foreach ($packages->filter(fn ($package) => $package->proof_result !== null) as $known) {
                        if (! collect($inventory)->contains(fn ($entry) => $entry['package_id'] === $known->id && $entry['manifest_sha256'] === $known->manifest_sha256)) {
                            $issues[] = 'Package missing from protected inventory: '.$known->id;
                        }
                    }
                } catch (Throwable $exception) {
                    $issues[] = 'Protected inventory could not be checked: '.$exception->getMessage();
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
                'scope' => 'integrity-and-completeness', 'proofs_checked' => collect($checks)->contains(fn (array $check): bool => $check['proof_checked'] ?? false), 'valid' => $issues === [], 'checks' => $checks, 'issues' => $issues,
                'recovery_attestation' => $organizationRecovery, 'recovery_attestation_is_operator_supplied' => $recovery !== null,
            ];
            $audit->record($organization->id, null, 'control.'.$report['type'], null, $report);
            $this->line(json_encode($report, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            $failed = $failed || $issues !== [];
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
