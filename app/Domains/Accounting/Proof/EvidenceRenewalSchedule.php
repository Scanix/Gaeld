<?php

namespace App\Domains\Accounting\Proof;

use DateTimeImmutable;

final class EvidenceRenewalSchedule
{
    /** @param array<string, mixed> $verification */
    public static function next(string $directory, array $verification): string
    {
        $due = (new DateTimeImmutable($verification['latest_timestamp'] ?? $verification['timestamp']))->modify('+'.(int) config('archiving.timestamp.renew_after_days', 300).' days');
        $certificates = glob($directory.'/evidence/*.pem') ?: [];
        $renewals = glob($directory.'/evidence/renewals/*/renewal.certificates.pem') ?: [];
        if ($renewals !== []) {
            sort($renewals, SORT_STRING);
            $certificates = [end($renewals)];
        }
        foreach ($certificates as $path) {
            preg_match_all('/-----BEGIN CERTIFICATE-----.*?-----END CERTIFICATE-----/s', EvidenceFiles::read($path), $matches);
            foreach ($matches[0] as $pem) {
                $certificate = openssl_x509_parse($pem);
                if (is_array($certificate) && isset($certificate['validTo_time_t'])) {
                    $expiry = (new DateTimeImmutable('@'.$certificate['validTo_time_t']))->modify('-30 days');
                    if ($expiry < $due) {
                        $due = $expiry;
                    }
                }
            }
        }

        return $due->format(DATE_ATOM);
    }
}
