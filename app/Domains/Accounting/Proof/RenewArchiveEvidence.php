<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\ArchiveProofVerifier;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\Contracts\TimestampAuthority;
use RuntimeException;

class RenewArchiveEvidence
{
    public function __construct(
        private readonly ArchiveProofVerifier $verifier,
        private readonly TimestampAuthority $timestamps,
        private readonly ProtectedArchiveStorage $storage,
    ) {}

    /** @return array<string, mixed> */
    public function renew(string $directory, string $renewalId, string $retainUntil, string $expectedManifestHash): array
    {
        if (! preg_match('/^[0-9]{8}T[0-9]{6}Z-[a-f0-9]{8}$/D', $renewalId)) {
            throw new RuntimeException('Renewal identifier must be an ordered UTC timestamp and random suffix.');
        }
        $lock = fopen(dirname($directory).'/.'.basename($directory).'-proof.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Another process is handling this archive package.');
        }
        try {
            $inspector = (new StandaloneArchiveVerifier)->verifier();
            $inspector->inspect($directory, $expectedManifestHash);
            $prefix = 'evidence/renewals/'.$renewalId.'/';
            $indexPath = $directory.'/'.$prefix.'index.json';
            if (! is_file($indexPath)) {
                $this->verifier->verify($directory, $expectedManifestHash);
                $files = [];
                foreach ($inspector->files($directory) as $relative) {
                    if (str_ends_with($relative, '/storage-receipt.json')) {
                        continue;
                    }
                    $files[] = ['path' => $relative, 'sha256' => EvidenceFiles::hash($inspector->path($directory, $relative))];
                }
                EvidenceFiles::writeOnce($indexPath, json_encode(['schema_version' => '1.0.0', 'manifest_sha256' => $expectedManifestHash, 'files' => $files], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            $timestamp = $this->timestamps->timestamp($indexPath, $directory.'/'.$prefix.'renewal');
            $verification = $this->verifier->verify($directory, $expectedManifestHash);
            $receiptPath = $directory.'/'.$prefix.'storage-receipt.json';
            if (is_file($receiptPath)) {
                $receipt = json_decode(EvidenceFiles::read($receiptPath), true, 512, JSON_THROW_ON_ERROR);
                $this->storage->verify($directory, $receipt);
            } else {
                $receipt = $this->storage->deposit($directory, $retainUntil);
                EvidenceFiles::writeOnce($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }

            return ['renewal_id' => $renewalId, 'timestamp' => $timestamp, 'verification' => $verification, 'storage' => $receipt, 'next_renewal_at' => EvidenceRenewalSchedule::next($directory, $verification)];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
