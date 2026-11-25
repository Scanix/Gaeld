<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('archive:retain {package : Package UUID} {--until= : Extend minimum retention to YYYY-MM-DD} {--hold : Suspend destruction} {--release-hold : Release a recorded hold} {--reason= : Required reason for the change}')]
#[Description('Extend archive retention or record a legal hold without deleting any evidence')]
class ArchiveRetainCommand extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(ProtectedArchiveStorage $storage, ArchiveAccessLog $audit): int
    {
        if (strlen(trim((string) $this->option('reason'))) < 10 || ($this->option('hold') && $this->option('release-hold'))
            || (! $this->option('until') && ! $this->option('hold') && ! $this->option('release-hold'))) {
            $this->error('Choose an extension or one hold action and supply a reason of at least 10 characters.');

            return self::FAILURE;
        }
        $lock = 'archive-proof:'.$this->argument('package');
        $acquired = DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock]);
        if (! $acquired->acquired) {
            $this->error('This package is already being processed.');

            return self::FAILURE;
        }
        $remoteAttempted = false;
        try {
            DB::transaction(function () use ($storage, $audit, &$remoteAttempted): void {
                $package = ArchivePackage::withoutGlobalScopes()->lockForUpdate()->findOrFail($this->argument('package'));
                $until = $package->retention_until;
                if ($this->option('until')) {
                    $input = (string) $this->option('until');
                    if (! preg_match('/^\d{4}-\d{2}-\d{2}$/D', $input)) {
                        throw new \InvalidArgumentException('Use YYYY-MM-DD for retention.');
                    }
                    $until = Carbon::createFromFormat('!Y-m-d', $input)->endOfDay();
                    if ($until->format('Y-m-d') !== $input || $until->lt($package->retention_until)) {
                        throw new \InvalidArgumentException('Retention may only be extended.');
                    }
                }
                $hold = $this->option('hold') ? true : ($this->option('release-hold') ? false : $package->legal_hold);
                $proof = $package->proof_result;
                if (isset($proof['storage'])) {
                    $remoteAttempted = true;
                    $proof['storage'] = $storage->protect($proof['storage'], $until->toIso8601String(), $hold);
                }
                $package->update(['retention_until' => $until, 'legal_hold' => $hold, 'proof_result' => $proof]);
                $audit->record($package->organization_id, $package->id, 'package.retention_changed', null, [
                    'retention_until' => $until->toIso8601String(), 'legal_hold' => $hold, 'reason' => $this->option('reason'),
                ]);
            });
        } catch (Throwable $exception) {
            if ($remoteAttempted) {
                $package = ArchivePackage::withoutGlobalScopes()->findOrFail($this->argument('package'));
                $package->update(['state' => 'incident', 'failure' => 'Remote retention change must be reconciled before retry.']);
                $audit->record($package->organization_id, $package->id, 'package.retention_incident', null, [
                    'error' => $exception::class, 'requested_until' => $this->option('until'),
                    'requested_hold' => (bool) $this->option('hold'), 'requested_release' => (bool) $this->option('release-hold'),
                ]);
            }
            $this->error($exception->getMessage());

            return self::FAILURE;
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
        $this->info('Retention recorded. No evidence has been deleted.');

        return self::SUCCESS;
    }
}
