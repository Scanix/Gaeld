<?php

namespace Tests\Unit\Proof;

use App\Domains\Accounting\Proof\S3ComplianceStorage;
use Aws\CommandInterface;
use Aws\Exception\AwsException;
use Aws\MockHandler;
use Aws\Result;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\Support\ArchiveProofFixtures;
use Tests\TestCase;

class S3ComplianceStorageTest extends TestCase
{
    use ArchiveProofFixtures;

    private string $directory;

    private array $objects = [];

    private array $calls = [];

    private bool $uncertainPut = false;

    private string $mode = 'COMPLIANCE';

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/gaeld-s3-proof-'.bin2hex(random_bytes(12));
        $this->fixtureManifest($this->directory.'/package');
        config(['archiving.s3.bucket' => 'preservation-test', 'archiving.s3.prefix' => 'archive']);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_deposit_retains_versions_checks_bytes_and_publishes_independent_catalog(): void
    {
        $storage = $this->storage();
        $receipt = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $this->assertCount(2, $receipt['objects']);
        $this->assertNotEmpty($receipt['catalog']['version_id']);
        $storage->verify($this->directory.'/package', $receipt);
        $inventory = $storage->inventory('organization-one');
        $this->assertSame($receipt['manifest_sha256'], $inventory[0]['manifest_sha256']);
        $this->assertSame('package-one', $inventory[0]['package_id']);
        $this->assertSame($inventory, $storage->inventory());
        $this->assertSame(3, count(array_filter($this->calls, fn (string $call): bool => $call === 'PutObject')));
        $again = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $this->assertSame($receipt, $again);
        $this->assertSame(3, count(array_filter($this->calls, fn (string $call): bool => $call === 'PutObject')));
    }

    public function test_uncertain_successful_put_is_reconciled_without_a_second_version(): void
    {
        $this->uncertainPut = true;
        $receipt = $this->storage()->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $this->assertCount(2, $receipt['objects']);
        $this->assertCount(3, $this->objects);
        $this->assertSame(3, count(array_filter($this->calls, fn (string $call): bool => $call === 'PutObject')));
    }

    public function test_governance_mode_cannot_be_reported_as_compliance(): void
    {
        $this->mode = 'GOVERNANCE';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('not protected');
        $this->storage()->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
    }

    public function test_shortened_remote_retention_is_rejected(): void
    {
        $storage = $this->storage();
        $receipt = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $first = array_key_first($this->objects);
        $this->objects[$first]['retention'] = new \DateTimeImmutable('2030-01-01');
        $this->expectException(RuntimeException::class);
        $storage->verify($this->directory.'/package', $receipt);
    }

    public function test_changed_remote_content_is_detected_even_with_original_metadata(): void
    {
        $storage = $this->storage();
        $receipt = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $first = array_key_first($this->objects);
        $this->objects[$first]['body'] = 'tampered';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Read-back');
        $storage->verify($this->directory.'/package', $receipt);
    }

    public function test_incomplete_receipt_is_rejected(): void
    {
        $storage = $this->storage();
        $receipt = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        array_pop($receipt['objects']);
        $this->expectException(RuntimeException::class);
        $storage->verify($this->directory.'/package', $receipt);
    }

    public function test_retention_extension_and_legal_hold_are_verified_and_catalogued(): void
    {
        $storage = $this->storage();
        $receipt = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $protected = $storage->protect($receipt, '2040-01-01T00:00:00Z', true);
        $this->assertTrue($protected['legal_hold']);
        $this->assertNotSame($receipt['catalog']['key'], $protected['catalog']['key']);
        $storage->verify($this->directory.'/package', $protected);
        $this->expectException(RuntimeException::class);
        $storage->protect($protected, '2039-01-01T00:00:00Z', true);
    }

    public function test_legal_hold_protects_all_historical_catalogs_after_evidence_is_added(): void
    {
        $storage = $this->storage();
        $original = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        mkdir($this->directory.'/package/evidence');
        file_put_contents($this->directory.'/package/evidence/renewal-proof.bin', 'additional retained evidence');
        $renewed = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $this->assertNotSame($original['catalog']['key'], $renewed['catalog']['key']);
        $protected = $storage->protect($renewed, '2040-01-01T00:00:00Z', true);
        foreach ([$original, $renewed, $protected] as $receipt) {
            $this->assertSame('ON', $this->objects[$receipt['catalog']['key']]['hold']);
            $this->assertSame('2040-01-01', $this->objects[$receipt['catalog']['key']]['retention']->format('Y-m-d'));
        }
        $storage->verify($this->directory.'/package', $protected);
    }

    public function test_restoring_an_old_package_and_its_old_receipt_cannot_hide_a_retained_renewal(): void
    {
        $storage = $this->storage();
        $original = $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        File::copyDirectory($this->directory.'/package', $this->directory.'/older-copy');
        mkdir($this->directory.'/package/evidence');
        file_put_contents($this->directory.'/package/evidence/renewal-proof.bin', 'additional retained evidence');
        $storage->deposit($this->directory.'/package', '2038-01-01T00:00:00Z');
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('omits evidence already recorded');
        $storage->verify($this->directory.'/older-copy', $original);
    }

    private function storage(): S3ComplianceStorage
    {
        $mock = new MockHandler;
        for ($index = 0; $index < 200; $index++) {
            $mock->append(function (CommandInterface $command) {
                $name = $command->getName();
                $this->calls[] = $name;
                $key = $command['Key'];
                if ($name === 'GetBucketVersioning') {
                    return new Result(['Status' => 'Enabled']);
                }
                if ($name === 'GetObjectLockConfiguration') {
                    return new Result(['ObjectLockConfiguration' => ['ObjectLockEnabled' => 'Enabled']]);
                }
                if ($name === 'ListObjectVersions') {
                    $versions = [];
                    foreach ($this->objects as $objectKey => $object) {
                        if (str_starts_with($objectKey, $command['Prefix'])) {
                            $versions[] = ['Key' => $objectKey, 'VersionId' => $object['version']];
                        }
                    }

                    return new Result(['Versions' => $versions, 'IsTruncated' => false]);
                }
                if ($name === 'PutObject') {
                    $this->assertSame('*', $command['IfNoneMatch']);
                    $this->assertSame('COMPLIANCE', $command['ObjectLockMode']);
                    $body = is_resource($command['Body']) ? stream_get_contents($command['Body']) : (string) $command['Body'];
                    $this->objects[$key] = ['body' => $body, 'version' => 'version-'.count($this->objects), 'retention' => $command['ObjectLockRetainUntilDate'], 'hold' => 'OFF'];
                    if ($this->uncertainPut) {
                        $this->uncertainPut = false;

                        return new AwsException('Connection lost after upload', $command, ['connection_error' => true]);
                    }

                    return new Result(['VersionId' => $this->objects[$key]['version']]);
                }
                if (! isset($this->objects[$key])) {
                    return new AwsException('Not found', $command, ['response' => new Response(404)]);
                }
                $object = $this->objects[$key];
                if ($name === 'HeadObject') {
                    return new Result(['VersionId' => $object['version']]);
                }
                $this->assertSame($object['version'], $command['VersionId']);
                if ($name === 'GetObjectRetention') {
                    return new Result(['Retention' => ['Mode' => $this->mode, 'RetainUntilDate' => $object['retention']]]);
                }
                if ($name === 'PutObjectRetention') {
                    $this->objects[$key]['retention'] = $command['Retention']['RetainUntilDate'];

                    return new Result;
                }
                if ($name === 'PutObjectLegalHold') {
                    $this->objects[$key]['hold'] = $command['LegalHold']['Status'];

                    return new Result;
                }
                if ($name === 'GetObjectLegalHold') {
                    return new Result(['LegalHold' => ['Status' => $object['hold']]]);
                }
                if ($name === 'GetObject') {
                    return new Result(['Body' => Utils::streamFor($object['body'])]);
                }
                throw new RuntimeException('Unexpected S3 command: '.$name);
            });
        }

        return new S3ComplianceStorage(new S3Client(['region' => 'eu-central-1', 'version' => '2006-03-01', 'credentials' => ['key' => 'fixture', 'secret' => 'fixture'], 'handler' => $mock, 'retries' => 0]));
    }
}
