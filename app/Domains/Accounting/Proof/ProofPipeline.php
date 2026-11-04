<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\ArchiveProofVerifier;
use App\Domains\Accounting\Proof\Contracts\ManifestSigner;
use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\Contracts\TimestampAuthority;
use DateTimeImmutable;
use RuntimeException;

class ProofPipeline
{
    public function __construct(
        private readonly ManifestSigner $signer,
        private readonly TimestampAuthority $timestamps,
        private readonly ProtectedArchiveStorage $storage,
        private readonly ArchiveProofVerifier $verifier,
        private readonly PdfArchivalConverter $pdf,
    ) {}

    /** @return array<string, mixed> */
    public function process(string $directory, string $retainUntil, ?callable $stage = null): array
    {
        $lock = fopen(dirname($directory).'/.'.basename($directory).'-proof.lock', 'c');
        if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
            if (is_resource($lock)) {
                fclose($lock);
            }
            throw new RuntimeException('Another process is handling this archive package.');
        }
        try {
            $inspector = (new StandaloneArchiveVerifier)->verifier();
            $manifest = $inspector->inspect($directory);
            $inspector->validateProfile($manifest);
            if (new DateTimeImmutable($retainUntil) < new DateTimeImmutable($manifest['retention_until'])) {
                throw new RuntimeException('The deposit cannot shorten the signed retention period.');
            }
            $manifestHash = EvidenceFiles::hash($directory.'/manifest.json');
            $reports = 0;
            foreach ($manifest['files'] as $file) {
                if (($file['role'] ?? '') === 'report') {
                    $this->pdf->validate($inspector->path($directory, $file['path']));
                    $reports++;
                }
            }
            if ($reports === 0) {
                throw new RuntimeException('An archive needs PDF/A reports before it can be sealed.');
            }
            $stage && $stage('validated');
            if (is_file($directory.'/evidence/manifest.p7s') && is_file($directory.'/evidence/manifest.tsr')) {
                $signature = ['path' => 'evidence/manifest.p7s', 'sha256' => EvidenceFiles::hash($directory.'/evidence/manifest.p7s'), 'format' => 'CMS-detached'];
                $timestamp = ['response' => 'manifest.tsr', 'sha256' => EvidenceFiles::hash($directory.'/evidence/manifest.tsr'), 'protocol' => 'RFC3161'];
            } else {
                $signature = $this->signer->sign($directory.'/manifest.json', $directory.'/evidence');
                $timestamp = $this->timestamps->timestamp($directory.'/evidence/manifest.p7s', $directory.'/evidence/manifest');
            }
            $verification = $this->verifier->verify($directory, $manifestHash);
            $stage && $stage('sealed');
            $receiptPath = $directory.'/evidence/storage-receipt.json';
            if (is_file($receiptPath)) {
                $receipt = json_decode(EvidenceFiles::read($receiptPath), true, 512, JSON_THROW_ON_ERROR);
                $this->storage->verify($directory, $receipt);
            } else {
                $receipt = $this->storage->deposit($directory, $retainUntil);
                EvidenceFiles::writeOnce($receiptPath, json_encode($receipt, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
            }
            $stage && $stage('deposited');
            $stage && $stage('verified');

            return ['state' => 'verified', 'manifest_sha256' => $manifestHash, 'signature' => $signature, 'timestamp' => $timestamp, 'storage' => $receipt, 'verification' => $verification, 'next_renewal_at' => EvidenceRenewalSchedule::next($directory, $verification)];
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }
}
