<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\ArchivePackage;
use App\Domains\Accounting\Proof\Contracts\ArchiveProofVerifier;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\ProofPipeline;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ArchiveProofService
{
    public function __construct(
        private readonly ProofPipeline $pipeline,
        private readonly ArchiveProofVerifier $verifier,
        private readonly ProtectedArchiveStorage $storage,
        private readonly ArchiveAccessLog $audit,
    ) {}

    public function seal(ArchivePackage $package): ArchivePackage
    {
        if (! $package->archival_pdf || ! $package->manifest_sha256 || ! $package->package_path) {
            throw new \LogicException('A validated archival-PDF package is required before sealing.');
        }
        $lock = 'archive-proof:'.$package->id;
        $acquired = DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock]);
        if (! $acquired->acquired) {
            throw new \RuntimeException('This package is already being processed.');
        }

        try {
            $package->refresh();
            $directory = Storage::disk('local')->path($package->package_path);
            $manifestHash = is_file($directory.'/manifest.json') ? hash_file('sha256', $directory.'/manifest.json') : false;
            if (! is_string($manifestHash) || ! hash_equals($package->manifest_sha256, $manifestHash)) {
                throw new \RuntimeException('The prepared manifest is missing or has changed.');
            }
            if (isset($package->proof_result['storage'])) {
                $check = $this->check($package);
                if (! $check['valid']) {
                    throw new \RuntimeException(implode('; ', $check['issues']));
                }
                $package->update(['state' => 'verified', 'failure' => null]);

                return $package->refresh();
            }
            $package->update(['state' => 'sealing', 'failure' => null]);
            $result = $this->pipeline->process($directory, $package->retention_until->toIso8601String(), function (string $stage) use ($package): void {
                $values = ['state' => $stage];
                if ($stage === 'sealed') {
                    $values['sealed_at'] = $package->sealed_at ?? now();
                }
                if ($stage === 'deposited') {
                    $values['deposited_at'] = $package->deposited_at ?? now();
                }
                $package->update($values);
                $this->audit->record($package->organization_id, $package->id, 'proof.'.$stage);
            });
            if ($package->legal_hold) {
                $result['storage'] = $this->storage->protect($result['storage'], $package->retention_until->toIso8601String(), true);
            }
            $package->update([
                'state' => 'verified', 'proof_result' => $result, 'verified_at' => now(),
                'check_due_at' => $result['next_renewal_at'] ?? now()->addDays(300), 'failure' => null,
            ]);

            return $package->refresh();
        } catch (Throwable $exception) {
            $package->update(['state' => 'incident', 'failure' => mb_substr($exception->getMessage(), 0, 2000)]);
            $this->audit->record($package->organization_id, $package->id, 'proof.incident', null, ['error' => $exception::class]);
            throw $exception;
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    /** @return array{valid: bool, issues: array<string>, proof_checked: bool} */
    public function check(ArchivePackage $package): array
    {
        $result = app(ArchivePackageService::class)->verify($package);
        $result['proof_checked'] = false;
        if ($result['valid'] && $package->archival_pdf) {
            try {
                $directory = Storage::disk('local')->path($package->package_path);
                $verification = $this->verifier->verify($directory, $package->manifest_sha256);
                if (! ($verification['valid'] ?? false) || empty($package->proof_result['storage'])) {
                    throw new \RuntimeException('Complete proof and protected-storage receipt are required.');
                }
                $this->storage->verify($directory, $package->proof_result['storage']);
                $result['proof_checked'] = true;
            } catch (Throwable $exception) {
                $result['valid'] = false;
                $result['issues'][] = $exception->getMessage();
            }
        }
        if (! $result['valid']) {
            $package->update(['state' => 'incident', 'failure' => implode('; ', $result['issues'])]);
        }
        $this->audit->record($package->organization_id, $package->id, 'package.periodic_check', null, $result);

        return $result;
    }
}
