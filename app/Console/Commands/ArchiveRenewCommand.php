<?php

namespace App\Console\Commands;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\RenewArchiveEvidence;
use App\Domains\Accounting\Services\ArchiveAccessLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

#[Signature('archive:renew {package? : Package UUID} {--due : Renew every package with an overdue proof check} {--renewal-id= : Resume an existing renewal identifier}')]
#[Description('Append timestamp evidence and protect it without replacing previous archive proofs')]
class ArchiveRenewCommand extends Command
{
    public function handle(RenewArchiveEvidence $renewal, ProtectedArchiveStorage $storage, ArchiveAccessLog $audit): int
    {
        if ((! $this->argument('package') && ! $this->option('due')) || ($this->option('due') && $this->option('renewal-id'))) {
            $this->error('Specify a package UUID or --due. An explicit renewal identifier requires one package.');

            return self::FAILURE;
        }
        $query = ArchivePackage::withoutGlobalScopes()->whereNotNull('proof_result')->where('archival_pdf', true)
            ->when($this->argument('package'), fn ($query) => $query->whereKey($this->argument('package')))
            ->when($this->option('due'), fn ($query) => $query->where('check_due_at', '<=', now()));
        $failed = false;
        $processed = 0;
        foreach ($query->lazyById(50) as $package) {
            $lock = 'archive-proof:'.$package->id;
            $acquired = DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock]);
            if (! $acquired->acquired) {
                $this->error('Archive is already being processed: '.$package->id);
                $failed = true;

                continue;
            }
            try {
                $package->refresh();
                $proof = $package->proof_result;
                if (empty($proof['storage'])) {
                    throw new \RuntimeException('A protected package receipt is required before renewing proofs.');
                }
                $pending = $proof['renewal_pending'] ?? null;
                $requested = $this->option('renewal-id');
                if ($pending && $requested && $pending !== $requested) {
                    throw new \RuntimeException('Resume the recorded pending renewal before creating another.');
                }
                $renewalId = $pending ?? $requested ?? gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4));
                $proof['renewal_pending'] = $renewalId;
                $package->update(['proof_result' => $proof, 'state' => 'renewing']);
                $audit->record($package->organization_id, $package->id, 'proof.renewal_started', null, ['renewal_id' => $renewalId]);
                $result = $renewal->renew(Storage::disk('local')->path($package->package_path), $renewalId, $package->retention_until->toIso8601String(), $package->manifest_sha256);
                if ($package->legal_hold) {
                    $result['storage'] = $storage->protect($result['storage'], $package->retention_until->toIso8601String(), true);
                }
                unset($proof['renewal_pending']);
                $proof['storage'] = $result['storage'];
                $proof['verification'] = $result['verification'];
                $proof['next_renewal_at'] = $result['next_renewal_at'];
                $proof['renewals'][$renewalId] = $result;
                $package->update(['proof_result' => $proof, 'state' => 'verified', 'failure' => null, 'verified_at' => now(), 'check_due_at' => $result['next_renewal_at']]);
                $audit->record($package->organization_id, $package->id, 'proof.renewed', null, ['renewal_id' => $renewalId, 'next_renewal_at' => $result['next_renewal_at']]);
                $this->info('Archive evidence renewed: '.$package->id);
                $processed++;
            } catch (Throwable $exception) {
                $package->update(['state' => 'incident', 'failure' => mb_substr($exception->getMessage(), 0, 2000)]);
                $audit->record($package->organization_id, $package->id, 'proof.renewal_failed', null, ['error' => $exception::class]);
                $this->error('Archive renewal requires attention: '.$package->id);
                $failed = true;
            } finally {
                DB::select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
            }
        }
        if ($this->argument('package') && $processed === 0) {
            $this->error('No package was renewed.');

            return self::FAILURE;
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
