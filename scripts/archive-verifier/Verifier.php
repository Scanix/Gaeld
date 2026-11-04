<?php

namespace Gaeld\ArchiveVerifier;

use DateTimeImmutable;
use DirectoryIterator;
use RuntimeException;

/** Independent of Laravel, its database, Composer and the EE plugin. */
final class Verifier
{
    /** @param array<string, string> $options */
    public function __construct(private readonly array $options = []) {}

    private function read(string $path): string
    {
        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read archive content.');
        }

        return $contents;
    }

    private function hash(string $path): string
    {
        $hash = hash_file('sha256', $path);
        if ($hash === false) {
            throw new RuntimeException('Unable to hash archive content.');
        }

        return $hash;
    }

    private function size(string $path): int
    {
        $size = filesize($path);
        if ($size === false) {
            throw new RuntimeException('Unable to measure archive content.');
        }

        return $size;
    }

    private function temporary(string $prefix): string
    {
        $path = tempnam(sys_get_temp_dir(), $prefix);
        if ($path === false) {
            throw new RuntimeException('Unable to allocate verification storage.');
        }

        return $path;
    }

    /** @return array<string, mixed> */
    public function inspect(string $directory, ?string $expectedManifestHash = null): array
    {
        $manifestPath = $this->path($directory, 'manifest.json');
        if ($this->size($manifestPath) > 64 * 1024 * 1024) {
            throw new RuntimeException('Archive manifest exceeds the verifier limit.');
        }
        $hash = $this->hash($manifestPath);
        if ($expectedManifestHash !== null && ! hash_equals($expectedManifestHash, $hash)) {
            throw new RuntimeException('The archive does not match the independently retained manifest hash.');
        }
        $manifest = json_decode($this->read($manifestPath), true, 512, JSON_THROW_ON_ERROR);
        if (($manifest['schema_version'] ?? null) !== '1.0.0' || ! in_array($manifest['proof_profile'] ?? null, ['gaeld-content-package/1.0.0', 'gaeld-sealed-package/1.0.0'], true) || ! is_string($manifest['package_id'] ?? null) || $manifest['package_id'] === '' || ! is_string($manifest['organization_id'] ?? null) || $manifest['organization_id'] === '' || ! is_array($manifest['files'] ?? null) || count($manifest['files']) === 0) {
            throw new RuntimeException('Unsupported or incomplete archive manifest.');
        }
        $paths = ['manifest.json' => true];
        foreach ($manifest['files'] as $file) {
            $relative = $file['path'] ?? '';
            if (! is_string($relative) || isset($paths[$relative]) || ! is_int($file['size'] ?? null) || $file['size'] < 0 || ! preg_match('/^[a-f0-9]{64}$/D', $file['sha256'] ?? '')) {
                throw new RuntimeException('The manifest contains an invalid or duplicate inventory entry.');
            }
            $path = $this->path($directory, $relative);
            if ($this->size($path) !== $file['size'] || ! hash_equals($file['sha256'], $this->hash($path))) {
                throw new RuntimeException('Archive content failed verification: '.$relative);
            }
            $paths[$relative] = true;
        }
        foreach ($this->files($directory) as $relative) {
            if (! isset($paths[$relative]) && (($manifest['proof_profile'] ?? null) !== 'gaeld-sealed-package/1.0.0' || ! str_starts_with($relative, 'evidence/'))) {
                throw new RuntimeException('An archive content file is missing from the inventory: '.$relative);
            }
        }

        return $manifest;
    }

    /** @return array<string, mixed> */
    public function verify(string $directory, ?string $expectedManifestHash = null): array
    {
        $manifest = $this->inspect($directory, $expectedManifestHash);
        $this->validateProfile($manifest);
        $signerTrust = $this->externalTrust($directory, 'signer_trust');
        $tsaTrust = $this->externalTrust($directory, 'tsa_trust');
        $fingerprints = array_map(fn (string $value): string => strtolower(str_replace(':', '', trim($value))), explode(',', $this->options['signer_fingerprint'] ?? ''));
        if (count(array_filter($fingerprints, fn (string $value): bool => preg_match('/^[a-f0-9]{64}$/D', $value) === 1)) !== count($fingerprints)) {
            throw new RuntimeException('An independently supplied signer fingerprint is required.');
        }
        $openssl = $this->options['openssl'] ?? 'openssl';
        $signature = $this->path($directory, 'evidence/manifest.p7s');
        $timestamp = $this->verifyTimestamp($directory, 'evidence/manifest', $signature, $tsaTrust);
        $signer = $this->temporary('gaeld-verify-cert-');
        $chain = $this->temporary('gaeld-verify-chain-');
        try {
            $this->run([$openssl, 'cms', '-verify', '-binary', '-cades', '-inform', 'DER', '-in', $signature, '-content', $this->path($directory, 'manifest.json'), '-CAfile', $signerTrust, '-no-CApath', '-no-CAstore', '-purpose', 'any', '-attime', (string) $timestamp, '-signer', $signer, '-certsout', $chain, '-out', '/dev/null']);
            if (! in_array(openssl_x509_fingerprint($this->read($signer), 'sha256'), $fingerprints, true)) {
                throw new RuntimeException('The signer identity does not match the external trust policy.');
            }
            $this->run([$openssl, 'verify', '-CAfile', $signerTrust, '-no-CApath', '-no-CAstore', '-untrusted', $chain, '-CRLfile', $this->path($directory, 'evidence/signer.crl.pem'), '-crl_check_all', '-attime', (string) $timestamp, '-purpose', 'any', $signer]);
        } finally {
            @unlink($signer);
            @unlink($chain);
        }
        $reports = 0;
        foreach ($manifest['files'] as $file) {
            if (($file['role'] ?? '') !== 'report') {
                continue;
            }
            $this->verifyPdf($this->path($directory, $file['path']));
            $reports++;
        }
        if ($reports === 0) {
            throw new RuntimeException('The preservation profile requires validated PDF/A accounting reports.');
        }
        $renewals = $this->verifyRenewals($directory, $tsaTrust, $timestamp);

        return ['valid' => true, 'profile' => 'gaeld-sealed-package/1.0.0', 'package_id' => $manifest['package_id'], 'organization_id' => $manifest['organization_id'], 'manifest_sha256' => $this->hash($directory.'/manifest.json'), 'files_checked' => count($manifest['files']), 'pdfa_reports_checked' => $reports, 'timestamp' => gmdate(DATE_ATOM, $timestamp), 'latest_timestamp' => gmdate(DATE_ATOM, $renewals['latest_timestamp']), 'renewals_checked' => $renewals['count'], 'inventory_pinned' => $expectedManifestHash !== null, 'storage_protection_checked' => false, 'latest_evidence_pinned' => false];
    }

    /** @param array<string, mixed> $manifest */
    public function validateProfile(array $manifest): void
    {
        $year = $manifest['fiscal_year'] ?? [];
        if (($manifest['proof_profile'] ?? null) !== 'gaeld-sealed-package/1.0.0' || ! is_int($manifest['version'] ?? null) || $manifest['version'] < 1 || empty($year['id'])
            || ($manifest['report_profile'] ?? null) !== 'PDF/A-2b') {
            throw new RuntimeException('The sealed package needs an exact fiscal-year identity, version and PDF/A report profile.');
        }
        foreach (['start_date', 'end_date'] as $field) {
            $value = $year[$field] ?? null;
            $date = is_string($value) ? DateTimeImmutable::createFromFormat('!Y-m-d', $value) : false;
            if ($date === false || $date->format('Y-m-d') !== $value) {
                throw new RuntimeException('The sealed package has an invalid fiscal-year boundary.');
            }
        }
        if ($year['end_date'] < $year['start_date'] || ! is_string($manifest['retention_until'] ?? null)
            || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $manifest['retention_until'])) {
            throw new RuntimeException('The sealed package has an invalid fiscal-year or retention period.');
        }
        $minimum = (new DateTimeImmutable($year['end_date']))->modify('+10 years')->format('Y-m-d');
        if ((new DateTimeImmutable($manifest['retention_until']))->format('Y-m-d') < $minimum) {
            throw new RuntimeException('The sealed package retention is shorter than its minimum fiscal-year policy.');
        }
        $reports = array_column(array_filter($manifest['files'], fn (array $file): bool => ($file['role'] ?? '') === 'report'), 'path');
        foreach (['reports/journal.pdf', 'reports/balance-sheet.pdf', 'reports/profit-and-loss.pdf'] as $required) {
            if (! in_array($required, $reports, true)) {
                throw new RuntimeException('A required annual accounting report is missing: '.$required);
            }
        }
    }

    /** @return list<string> */
    public function files(string $directory, string $prefix = ''): array
    {
        $files = [];
        foreach (new DirectoryIterator($directory) as $entry) {
            if ($entry->isDot()) {
                continue;
            }
            if ($entry->isLink()) {
                throw new RuntimeException('Archive packages cannot contain symbolic links.');
            }
            $relative = $prefix.$entry->getFilename();
            if ($entry->isDir()) {
                $files = [...$files, ...$this->files($entry->getPathname(), $relative.'/')];
            } elseif ($entry->isFile()) {
                $files[] = $relative;
            } else {
                throw new RuntimeException('Archive contains an unsupported filesystem entry.');
            }
        }
        sort($files, SORT_STRING);

        return $files;
    }

    public function path(string $directory, string $relative): string
    {
        if ($relative === '' || str_contains($relative, '\\') || str_contains($relative, "\0") || str_starts_with($relative, '/') || preg_match('/(^|\/)\.\.?($|\/)|^[A-Za-z]:|\/\//', $relative)) {
            throw new RuntimeException('Unsafe archive inventory path.');
        }
        $root = realpath($directory);
        $path = realpath($directory.'/'.$relative);
        if ($root === false || $path === false || ! str_starts_with($path, $root.DIRECTORY_SEPARATOR) || ! is_file($path)) {
            throw new RuntimeException('Archive inventory file is missing or outside the package.');
        }
        $cursor = $directory;
        foreach (explode('/', $relative) as $component) {
            $cursor .= '/'.$component;
            if (is_link($cursor)) {
                throw new RuntimeException('Archive inventory paths cannot contain symbolic links.');
            }
        }

        return $path;
    }

    /** @param list<string> $arguments */
    public function run(array $arguments): string
    {
        $process = proc_open($arguments, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
        if (! is_resource($process)) {
            throw new RuntimeException('Unable to run the archive verifier dependency.');
        }
        fclose($pipes[0]);
        stream_set_blocking($pipes[1], false);
        stream_set_blocking($pipes[2], false);
        $output = '';
        $deadline = microtime(true) + 120;
        do {
            $output .= stream_get_contents($pipes[1]);
            stream_get_contents($pipes[2]);
            $status = proc_get_status($process);
            if (! $status['running']) {
                break;
            }
            if (strlen($output) > 16 * 1024 * 1024 || microtime(true) >= $deadline) {
                proc_terminate($process, 9);
                fclose($pipes[1]);
                fclose($pipes[2]);
                proc_close($process);
                throw new RuntimeException('Archive verification dependency exceeded its limit.');
            }
            usleep(10000);
        } while (true);
        $output .= stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        proc_close($process);
        if ($status['exitcode'] !== 0) {
            throw new RuntimeException('Archive cryptographic or PDF/A verification failed: '.basename($arguments[0]).'.');
        }

        return $output;
    }

    private function externalTrust(string $directory, string $key): string
    {
        $path = realpath($this->options[$key] ?? '');
        if ($path === false || ! is_file($path) || str_starts_with($path, realpath($directory).DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('Trust anchors must be provided independently, outside the archive package.');
        }

        return $path;
    }

    private function verifyTimestamp(string $directory, string $prefix, string $source, string $trust): int
    {
        $openssl = $this->options['openssl'] ?? 'openssl';
        $response = $this->path($directory, $prefix.'.tsr');
        $description = $this->run([$openssl, 'ts', '-reply', '-in', $response, '-text']);
        if (! preg_match('/^Time stamp: (.+)$/m', $description, $matches)) {
            throw new RuntimeException('The timestamp response has no usable generation time.');
        }
        $time = (new DateTimeImmutable(trim($matches[1])))->getTimestamp();
        if ($time > time() + 300) {
            throw new RuntimeException('The timestamp is in the future.');
        }
        $bundle = $this->temporary('gaeld-verifier-trust-');
        $emptyTrust = $bundle.'.empty';
        mkdir($emptyTrust, 0700);
        file_put_contents($bundle, $this->read($trust)."\n".$this->read($this->path($directory, $prefix.'.crl.pem')));
        try {
            $arguments = [$openssl, 'ts', '-verify', '-in', $response, '-CAfile', $bundle, '-CApath', $emptyTrust, '-CAstore', $emptyTrust, '-crl_check_all', '-attime', (string) $time];
            if (is_file($directory.'/'.$prefix.'.chain.pem')) {
                $arguments = [...$arguments, '-untrusted', $this->path($directory, $prefix.'.chain.pem')];
            }
            $this->run([...$arguments, '-data', $source]);
            $this->run([...$arguments, '-queryfile', $this->path($directory, $prefix.'.tsq')]);
        } finally {
            @unlink($bundle);
            @rmdir($emptyTrust);
        }

        return $time;
    }

    private function verifyPdf(string $path): void
    {
        $report = $this->run([$this->options['verapdf'] ?? 'verapdf', '--format', 'xml', '--flavour', '2b', $path]);
        $xml = new \DOMDocument;
        if (! @$xml->loadXML($report, LIBXML_NONET) || $xml->doctype !== null) {
            throw new RuntimeException('Invalid PDF/A validation report.');
        }
        $reports = (new \DOMXPath($xml))->query('//*[local-name()="validationReport"]');
        $report = $reports === false ? null : $reports->item(0);
        if ($reports === false || $reports->length !== 1 || ! $report instanceof \DOMElement || $report->getAttribute('isCompliant') !== 'true' || ! str_contains(strtoupper($report->getAttribute('profileName')), 'PDF/A-2B')) {
            throw new RuntimeException('A report is not valid PDF/A-2b.');
        }
    }

    /** @return array{count: int, latest_timestamp: int} */
    private function verifyRenewals(string $directory, string $trust, int $previousTime): array
    {
        $renewals = glob($directory.'/evidence/renewals/*/index.json') ?: [];
        sort($renewals, SORT_STRING);
        $expected = [];
        foreach ($this->files($directory) as $path) {
            if (! str_starts_with($path, 'evidence/renewals/') && ! str_ends_with($path, '/storage-receipt.json')) {
                $expected[] = $path;
            }
        }
        $count = 0;
        foreach ($renewals as $index) {
            $entries = json_decode($this->read($index), true, 512, JSON_THROW_ON_ERROR);
            if (($entries['schema_version'] ?? null) !== '1.0.0' || ($entries['manifest_sha256'] ?? null) !== $this->hash($directory.'/manifest.json')) {
                throw new RuntimeException('Renewal index does not identify the original manifest.');
            }
            $paths = [];
            foreach ($entries['files'] ?? [] as $file) {
                if (! hash_equals($file['sha256'], $this->hash($this->path($directory, $file['path'])))) {
                    throw new RuntimeException('Renewed archive evidence has changed.');
                }
                $paths[] = $file['path'];
            }
            sort($paths, SORT_STRING);
            sort($expected, SORT_STRING);
            if ($paths !== $expected) {
                throw new RuntimeException('Renewal does not cover all preceding package evidence.');
            }
            $prefix = substr($index, strlen(rtrim($directory, '/')) + 1, -strlen('index.json')).'renewal';
            $time = $this->verifyTimestamp($directory, $prefix, $index, $trust);
            if ($time < $previousTime) {
                throw new RuntimeException('Archive evidence renewals are not chronological.');
            }
            $previousTime = $time;
            $relativeDirectory = substr(dirname($index), strlen(rtrim($directory, '/')) + 1).'/';
            foreach ($this->files(dirname($index), $relativeDirectory) as $path) {
                if (! str_ends_with($path, '/storage-receipt.json')) {
                    $expected[] = $path;
                }
            }
            $count++;
        }

        return ['count' => $count, 'latest_timestamp' => $previousTime];
    }
}
