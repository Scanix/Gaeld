<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\ManifestSigner;
use RuntimeException;

class OpenSslManifestSigner implements ManifestSigner
{
    public function __construct(private readonly ProcessRunner $runner) {}

    public function sign(string $manifest, string $evidenceDirectory): array
    {
        $certificate = EvidenceFiles::required((string) config('archiving.signing.certificate'));
        $trust = EvidenceFiles::required((string) config('archiving.signing.trust_store'));
        $crl = EvidenceFiles::required((string) config('archiving.signing.crl'));
        $expected = array_map(fn (string $value): string => strtolower(str_replace(':', '', trim($value))), explode(',', (string) config('archiving.signing.allowed_fingerprint')));
        $fingerprint = openssl_x509_fingerprint(EvidenceFiles::read($certificate), 'sha256');
        if (! in_array($fingerprint, $expected, true)) {
            throw new RuntimeException('The archive signing certificate does not match the operator trust policy.');
        }
        $openssl = (string) config('archiving.openssl');
        $validation = [$openssl, 'verify', '-CAfile', $trust, '-no-CApath', '-no-CAstore', '-CRLfile', $crl, '-crl_check_all', '-purpose', 'any'];
        $chain = (string) config('archiving.signing.chain');
        if ($chain !== '') {
            $validation = [...$validation, '-untrusted', EvidenceFiles::required($chain)];
        }
        $this->runner->run([...$validation, $certificate]);
        EvidenceFiles::writeOnce($evidenceDirectory.'/signer.crl.pem', EvidenceFiles::read($crl));
        EvidenceFiles::writeOnce($evidenceDirectory.'/signer.pem', EvidenceFiles::read($certificate));
        $signature = $evidenceDirectory.'/manifest.p7s';
        if (! is_file($signature)) {
            $temporary = $signature.'.'.bin2hex(random_bytes(8)).'.tmp';
            $arguments = [$openssl, 'cms', '-sign', '-binary', '-cades', '-md', 'sha256', '-in', EvidenceFiles::required($manifest), '-signer', $certificate, '-inkey', EvidenceFiles::required((string) config('archiving.signing.private_key')), '-outform', 'DER', '-out', $temporary];
            if ($chain !== '') {
                $arguments = [...$arguments, '-certfile', $chain];
            }
            if (config('archiving.signing.passphrase_file')) {
                $arguments = [...$arguments, '-passin', 'file:'.EvidenceFiles::required((string) config('archiving.signing.passphrase_file'))];
            }
            try {
                $this->runner->run($arguments);
                EvidenceFiles::writeOnce($signature, EvidenceFiles::read($temporary));
            } finally {
                @unlink($temporary);
            }
        }
        $this->runner->run([$openssl, 'cms', '-verify', '-binary', '-cades', '-inform', 'DER', '-in', $signature, '-content', $manifest, '-CAfile', $trust, '-no-CApath', '-no-CAstore', '-purpose', 'any', '-out', '/dev/null']);

        return ['path' => 'evidence/manifest.p7s', 'sha256' => EvidenceFiles::hash($signature), 'signer_sha256' => $fingerprint, 'format' => 'CMS-detached'];
    }
}
