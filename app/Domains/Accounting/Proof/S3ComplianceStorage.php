<?php

namespace App\Domains\Accounting\Proof;

use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Aws\S3\S3ClientInterface;
use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;
use Throwable;

class S3ComplianceStorage implements ProtectedArchiveStorage
{
    public function __construct(private ?S3ClientInterface $client = null) {}

    public function deposit(string $directory, string $retainUntil): array
    {
        $retention = new DateTimeImmutable($retainUntil);
        if ($retention <= new DateTimeImmutable) {
            throw new RuntimeException('Protected storage requires a future retention date.');
        }
        $bucket = $this->bucket();
        $client = $this->client();
        if (($client->getBucketVersioning(['Bucket' => $bucket])['Status'] ?? null) !== 'Enabled' || ($client->getObjectLockConfiguration(['Bucket' => $bucket])['ObjectLockConfiguration']['ObjectLockEnabled'] ?? null) !== 'Enabled') {
            throw new RuntimeException('The archive bucket must have versioning and Object Lock enabled.');
        }
        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $manifest = $verifier->inspect($directory);
        $hash = EvidenceFiles::hash($directory.'/manifest.json');
        foreach (['organization_id', 'package_id'] as $key) {
            if (! preg_match('/^[a-zA-Z0-9_-]+$/D', $manifest[$key])) {
                throw new RuntimeException('Invalid archive storage identity.');
            }
        }
        $prefix = trim((string) config('archiving.s3.prefix'), '/').'/'.$manifest['organization_id'].'/'.$manifest['package_id'].'/'.$hash.'/';
        $receipt = ['schema_version' => '1.0.0', 'bucket' => $bucket, 'package_id' => $manifest['package_id'], 'organization_id' => $manifest['organization_id'], 'fiscal_year' => $manifest['fiscal_year'] ?? null, 'version' => $manifest['version'] ?? null, 'manifest_sha256' => $hash, 'retention_until' => $retention->format(DATE_ATOM), 'objects' => []];
        foreach ($verifier->files($directory) as $relative) {
            if (str_ends_with($relative, '/storage-receipt.json')) {
                continue;
            }
            $path = $verifier->path($directory, $relative);
            $key = $prefix.$relative;
            $sha256 = EvidenceFiles::hash($path);
            $existing = $this->head($bucket, $key);
            if ($existing === null) {
                try {
                    $stream = fopen($path, 'rb');
                    if ($stream === false) {
                        throw new RuntimeException('Unable to open package content for protected storage.');
                    }
                    try {
                        $client->putObject(['Bucket' => $bucket, 'Key' => $key, 'Body' => $stream, 'ContentLength' => EvidenceFiles::size($path), 'ContentMD5' => base64_encode(EvidenceFiles::hash($path, 'md5', true)), 'ChecksumSHA256' => base64_encode(EvidenceFiles::hash($path, 'sha256', true)), 'Metadata' => ['sha256' => $sha256], 'IfNoneMatch' => '*', 'ObjectLockMode' => 'COMPLIANCE', 'ObjectLockRetainUntilDate' => $retention]);
                    } finally {
                        if (is_resource($stream)) {
                            fclose($stream);
                        }
                    }
                } catch (Throwable $exception) {
                    // A timed-out PUT can have succeeded. Reconcile before any retry.
                    $existing = $this->head($bucket, $key);
                    if ($existing === null) {
                        throw $exception;
                    }
                }
                $existing ??= $this->head($bucket, $key);
            }
            if ($existing === null || empty($existing['VersionId']) || $existing['VersionId'] === 'null') {
                throw new RuntimeException('Protected storage did not return an immutable object version.');
            }
            $object = ['path' => $relative, 'key' => $key, 'version_id' => $existing['VersionId'], 'sha256' => $sha256, 'size' => EvidenceFiles::size($path)];
            $this->verifyObject($bucket, $object, $retention);
            $receipt['objects'][] = $object;
        }

        $receipt['catalog'] = $this->publishCatalog($receipt, $retention);

        return $receipt;
    }

    public function verify(string $directory, array $receipt): void
    {
        if (($receipt['bucket'] ?? null) !== $this->bucket() || ! hash_equals((string) ($receipt['manifest_sha256'] ?? ''), EvidenceFiles::hash($directory.'/manifest.json'))) {
            throw new RuntimeException('The protected storage receipt does not match this package.');
        }
        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $expected = array_values(array_filter($verifier->files($directory), fn (string $path): bool => ! str_ends_with($path, '/storage-receipt.json')));
        $observed = [];
        foreach ($receipt['objects'] ?? [] as $object) {
            $path = $verifier->path($directory, $object['path']);
            if (isset($observed[$object['path']]) || ! hash_equals($object['sha256'], EvidenceFiles::hash($path)) || $object['size'] !== EvidenceFiles::size($path)) {
                throw new RuntimeException('Protected storage inventory does not match local content.');
            }
            $observed[$object['path']] = true;
            $this->verifyObject($receipt['bucket'], $object, new DateTimeImmutable($receipt['retention_until']), $receipt['legal_hold'] ?? null);
        }
        $actual = array_keys($observed);
        sort($actual, SORT_STRING);
        if ($expected !== $actual) {
            throw new RuntimeException('The protected storage receipt is incomplete.');
        }
        if (empty($receipt['catalog'])) {
            throw new RuntimeException('The archive is missing its protected catalog record.');
        }
        $catalog = $receipt['catalog'];
        unset($receipt['catalog']);
        $payload = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (! hash_equals($catalog['sha256'], hash('sha256', $payload))) {
            throw new RuntimeException('The catalog does not match the retained package receipt.');
        }
        $this->verifyObject($receipt['bucket'], $catalog, new DateTimeImmutable($receipt['retention_until']), $receipt['legal_hold'] ?? null);
        $retainedVersions = [];
        foreach ($receipt['objects'] as $object) {
            $retainedVersions[$object['key']."\0".$object['version_id']] = $object['sha256'];
        }
        foreach ($this->inventory($receipt['organization_id']) as $historical) {
            if ($historical['package_id'] !== $receipt['package_id'] || $historical['manifest_sha256'] !== $receipt['manifest_sha256']) {
                continue;
            }
            foreach ($historical['objects'] as $object) {
                if (($retainedVersions[$object['key']."\0".$object['version_id']] ?? null) !== $object['sha256']) {
                    throw new RuntimeException('The local receipt omits evidence already recorded in the protected catalog.');
                }
            }
        }
    }

    public function protect(array $receipt, string $retainUntil, bool $legalHold): array
    {
        if (($receipt['bucket'] ?? null) !== $this->bucket() || empty($receipt['catalog'])) {
            throw new RuntimeException('A verified protected storage receipt is required.');
        }
        $retention = new DateTimeImmutable($retainUntil);
        if ($retention < new DateTimeImmutable($receipt['retention_until'])) {
            throw new RuntimeException('Compliance retention cannot be shortened.');
        }
        $versions = [];
        $history = array_filter($this->inventory($receipt['organization_id']), fn (array $entry): bool => $entry['package_id'] === $receipt['package_id'] && $entry['manifest_sha256'] === $receipt['manifest_sha256']);
        $history[] = $receipt;
        $contentPrefix = trim((string) config('archiving.s3.prefix'), '/').'/'.$receipt['organization_id'].'/'.$receipt['package_id'].'/'.$receipt['manifest_sha256'].'/';
        $catalogPrefix = trim((string) config('archiving.s3.prefix'), '/').'/catalog/'.$receipt['organization_id'].'/'.$receipt['package_id'].'/';
        foreach ($history as $entry) {
            if ($retention < new DateTimeImmutable($entry['retention_until'])) {
                throw new RuntimeException('Compliance retention cannot shorten any retained catalog version.');
            }
            foreach ([...$entry['objects'], $entry['catalog']] as $object) {
                if (! str_starts_with($object['key'], $contentPrefix) && ! str_starts_with($object['key'], $catalogPrefix)) {
                    throw new RuntimeException('A protected catalog object belongs to a different package.');
                }
                $versions[$object['key']."\0".$object['version_id']] = $object;
            }
        }
        foreach ($versions as $object) {
            $query = ['Bucket' => $receipt['bucket'], 'Key' => $object['key'], 'VersionId' => $object['version_id']];
            $this->client()->putObjectRetention([...$query, 'Retention' => ['Mode' => 'COMPLIANCE', 'RetainUntilDate' => $retention]]);
            $this->client()->putObjectLegalHold([...$query, 'LegalHold' => ['Status' => $legalHold ? 'ON' : 'OFF']]);
            $this->verifyObject($receipt['bucket'], $object, $retention, $legalHold);
        }
        unset($receipt['catalog']);
        $receipt['retention_until'] = $retention->format(DATE_ATOM);
        $receipt['legal_hold'] = $legalHold;
        $receipt['catalog'] = $this->publishCatalog($receipt, $retention);
        $this->client()->putObjectLegalHold(['Bucket' => $receipt['bucket'], 'Key' => $receipt['catalog']['key'], 'VersionId' => $receipt['catalog']['version_id'], 'LegalHold' => ['Status' => $legalHold ? 'ON' : 'OFF']]);
        $this->verifyObject($receipt['bucket'], $receipt['catalog'], $retention, $legalHold);

        return $receipt;
    }

    public function inventory(?string $organizationId = null): array
    {
        if ($organizationId !== null && ! preg_match('/^[a-zA-Z0-9_-]+$/D', $organizationId)) {
            throw new RuntimeException('Invalid organization inventory identity.');
        }
        $bucket = $this->bucket();
        $query = ['Bucket' => $bucket, 'Prefix' => trim((string) config('archiving.s3.prefix'), '/').'/catalog/'.($organizationId === null ? '' : $organizationId.'/')];
        $inventory = [];
        do {
            $page = $this->client()->listObjectVersions($query);
            foreach ($page['Versions'] ?? [] as $version) {
                $contents = (string) $this->client()->getObject(['Bucket' => $bucket, 'Key' => $version['Key'], 'VersionId' => $version['VersionId']])['Body'];
                $entry = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
                if (! is_string($entry['organization_id'] ?? null) || ! preg_match('/^[a-zA-Z0-9_-]+$/D', $entry['organization_id']) || ($organizationId !== null && $entry['organization_id'] !== $organizationId) || empty($entry['retention_until'])) {
                    throw new RuntimeException('Protected catalog contains an invalid organization record.');
                }
                $object = ['key' => $version['Key'], 'version_id' => $version['VersionId'], 'sha256' => hash('sha256', $contents), 'size' => strlen($contents)];
                $this->verifyObject($bucket, $object, new DateTimeImmutable($entry['retention_until']));
                $entry['catalog'] = $object;
                $inventory[] = $entry;
            }
            $query['KeyMarker'] = $page['NextKeyMarker'] ?? '';
            $query['VersionIdMarker'] = $page['NextVersionIdMarker'] ?? '';
        } while ($page['IsTruncated'] ?? false);

        return $inventory;
    }

    /** @param array<string, mixed> $receipt
     * @return array<string, mixed>
     */
    private function publishCatalog(array $receipt, DateTimeImmutable $retention): array
    {
        $payload = json_encode($receipt, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $sha256 = hash('sha256', $payload);
        $key = trim((string) config('archiving.s3.prefix'), '/').'/catalog/'.$receipt['organization_id'].'/'.$receipt['package_id'].'/'.$sha256.'.json';
        $existing = $this->head($receipt['bucket'], $key);
        if ($existing === null) {
            try {
                $this->client()->putObject(['Bucket' => $receipt['bucket'], 'Key' => $key, 'Body' => $payload, 'ContentMD5' => base64_encode(md5($payload, true)), 'ChecksumSHA256' => base64_encode(hash('sha256', $payload, true)), 'IfNoneMatch' => '*', 'ObjectLockMode' => 'COMPLIANCE', 'ObjectLockRetainUntilDate' => $retention, 'Metadata' => ['sha256' => $sha256]]);
            } catch (Throwable $exception) {
                $existing = $this->head($receipt['bucket'], $key);
                if ($existing === null) {
                    throw $exception;
                }
            }
            $existing ??= $this->head($receipt['bucket'], $key);
        }
        if (empty($existing['VersionId']) || $existing['VersionId'] === 'null') {
            throw new RuntimeException('The protected catalog has no immutable version.');
        }
        $object = ['key' => $key, 'version_id' => $existing['VersionId'], 'sha256' => $sha256, 'size' => strlen($payload)];
        $this->verifyObject($receipt['bucket'], $object, $retention);

        return $object;
    }

    /** @param array<string, mixed> $object */
    private function verifyObject(string $bucket, array $object, DateTimeImmutable $retention, ?bool $legalHold = null): void
    {
        $query = ['Bucket' => $bucket, 'Key' => $object['key'], 'VersionId' => $object['version_id']];
        $lock = $this->client()->getObjectRetention($query)['Retention'] ?? [];
        $lockedUntil = $lock['RetainUntilDate'] ?? null;
        if ($lockedUntil instanceof DateTimeInterface) {
            $lockedUntil = DateTimeImmutable::createFromInterface($lockedUntil);
        } elseif ($lockedUntil !== null) {
            $lockedUntil = new DateTimeImmutable((string) $lockedUntil);
        }
        if (($lock['Mode'] ?? null) !== 'COMPLIANCE' || $lockedUntil === null || $lockedUntil < $retention) {
            throw new RuntimeException('The stored object is not protected for the required retention period.');
        }
        if ($legalHold !== null && ($this->client()->getObjectLegalHold($query)['LegalHold']['Status'] ?? null) !== ($legalHold ? 'ON' : 'OFF')) {
            throw new RuntimeException('The stored object legal hold did not match the requested policy.');
        }
        $body = $this->client()->getObject($query)['Body'];
        $context = hash_init('sha256');
        $bytes = 0;
        while (! $body->eof()) {
            $chunk = $body->read(1024 * 1024);
            hash_update($context, $chunk);
            $bytes += strlen($chunk);
        }
        $body->close();
        if ($bytes !== $object['size'] || ! hash_equals($object['sha256'], hash_final($context))) {
            throw new RuntimeException('Read-back from protected storage failed integrity verification.');
        }
    }

    /** @return array<string, mixed>|null */
    private function head(string $bucket, string $key): ?array
    {
        try {
            return $this->client()->headObject(['Bucket' => $bucket, 'Key' => $key])->toArray();
        } catch (AwsException $exception) {
            if ($exception->getStatusCode() === 404) {
                return null;
            }

            throw $exception;
        }
    }

    private function bucket(): string
    {
        $bucket = (string) config('archiving.s3.bucket');
        if ($bucket === '') {
            throw new RuntimeException('No protected archive storage bucket is configured.');
        }

        return $bucket;
    }

    private function client(): S3ClientInterface
    {
        if ($this->client !== null) {
            return $this->client;
        }
        $options = ['version' => '2006-03-01', 'region' => config('archiving.s3.region'), 'use_path_style_endpoint' => (bool) config('archiving.s3.use_path_style_endpoint'), 'http' => ['connect_timeout' => 10, 'timeout' => 120], 'retries' => 0];
        if (config('archiving.s3.tls_ca_bundle')) {
            $options['http']['verify'] = EvidenceFiles::required((string) config('archiving.s3.tls_ca_bundle'));
        }
        if (config('archiving.s3.endpoint')) {
            $endpoint = (string) config('archiving.s3.endpoint');
            if (parse_url($endpoint, PHP_URL_SCHEME) !== 'https') {
                throw new RuntimeException('Protected archive storage requires HTTPS.');
            }
            $options['endpoint'] = $endpoint;
        }
        if (config('archiving.s3.key')) {
            $options['credentials'] = ['key' => config('archiving.s3.key'), 'secret' => config('archiving.s3.secret')];
        }

        return $this->client = new S3Client($options);
    }
}
