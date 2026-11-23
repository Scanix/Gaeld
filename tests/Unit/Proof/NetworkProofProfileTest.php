<?php

namespace Tests\Unit\Proof;

use App\Domains\Accounting\Proof\EvidenceFiles;
use App\Domains\Accounting\Proof\OpenSslManifestSigner;
use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\ProcessRunner;
use App\Domains\Accounting\Proof\ProofPipeline;
use App\Domains\Accounting\Proof\RenewArchiveEvidence;
use App\Domains\Accounting\Proof\Rfc3161TimestampAuthority;
use App\Domains\Accounting\Proof\S3ComplianceStorage;
use App\Domains\Accounting\Proof\StandaloneArchiveVerifier;
use Aws\Exception\AwsException;
use Aws\S3\S3Client;
use Dompdf\Dompdf;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Tests\TestCase;

class NetworkProofProfileTest extends TestCase
{
    private string $lab;

    /** @var array<string, mixed> */
    private array $settings;

    protected function setUp(): void
    {
        parent::setUp();
        $lab = getenv('GAELD_PROOF_LAB');
        if (! is_string($lab) || ! is_file($lab.'/settings.json')) {
            $this->markTestSkipped('Explicit isolated HTTPS TSA and S3 laboratory required; no provider is mocked by this test.');
        }
        $this->lab = $lab;
        $this->settings = json_decode(file_get_contents($lab.'/settings.json'), true, 512, JSON_THROW_ON_ERROR);
        config([
            'archiving.proofs_enabled' => true,
            'archiving.process_timeout' => 180,
            'archiving.pdfa.ghostscript' => $lab.'/ghostscript/bin/gs',
            'archiving.pdfa.verapdf' => $lab.'/bin/verapdf',
            'archiving.pdfa.icc_profile' => $lab.'/ghostscript/share/ghostscript/10.08.0/iccprofiles/srgb.icc',
            'archiving.signing.certificate' => $lab.'/pki/signer/leaf.pem',
            'archiving.signing.private_key' => $lab.'/pki/signer/leaf.key',
            'archiving.signing.chain' => $lab.'/pki/signer/ca.pem',
            'archiving.signing.trust_store' => $lab.'/pki/signer/ca.pem',
            'archiving.signing.crl' => $lab.'/pki/signer/ca.crl.pem',
            'archiving.signing.allowed_fingerprint' => openssl_x509_fingerprint(file_get_contents($lab.'/pki/signer/leaf.pem'), 'sha256'),
            'archiving.timestamp.url' => 'https://127.0.0.1:18443/',
            'archiving.timestamp.trust_store' => $lab.'/pki/tsa/ca.pem',
            'archiving.timestamp.chain' => $lab.'/pki/tsa/ca.pem',
            'archiving.timestamp.crl' => $lab.'/pki/tsa/ca.crl.pem',
            'archiving.timestamp.tls_ca_bundle' => $lab.'/pki/tls/ca.pem',
            'archiving.timestamp.basic_username_file' => $lab.'/pki/tsa/username',
            'archiving.timestamp.basic_password_file' => $lab.'/pki/tsa/password',
            'archiving.s3.endpoint' => 'https://127.0.0.1:19443',
            'archiving.s3.region' => $this->settings['region'],
            'archiving.s3.bucket' => $this->settings['bucket'],
            'archiving.s3.prefix' => $this->settings['prefix'],
            'archiving.s3.key' => $this->settings['access_key'],
            'archiving.s3.secret' => $this->settings['secret_key'],
            'archiving.s3.use_path_style_endpoint' => true,
            'archiving.s3.tls_ca_bundle' => $lab.'/pki/tls/ca.pem',
        ]);
    }

    public function test_actual_https_providers_pdfa_sealing_retention_renewal_and_independent_recovery(): void
    {
        $run = bin2hex(random_bytes(8));
        $directory = $this->lab.'/runs/'.$run;
        $package = $directory.'/package';
        mkdir($package.'/reports', 0700, true);
        $runner = new ProcessRunner;
        $pdf = new Dompdf;
        $pdf->loadHtml('<h1>SYNTHETIC historical financial statements</h1><p>Fixture only. No real company data. CHF 100.00</p>');
        $pdf->render();
        file_put_contents($directory.'/source.pdf', $pdf->output());
        $converter = new PdfArchivalConverter($runner);
        $converter->convert($directory.'/source.pdf', $package.'/reports/journal.pdf');
        $retention = now()->addDays(2)->startOfSecond()->toIso8601String();
        $manifest = [
            'schema_version' => '1.0.0', 'proof_profile' => 'gaeld-sealed-package/1.0.0',
            'package_id' => 'synthetic-'.$run, 'organization_id' => 'synthetic-organization-'.$run,
            'fiscal_year' => ['id' => 'synthetic-historical-year', 'start_date' => '2015-01-01', 'end_date' => '2015-12-31'],
            'version' => 1, 'retention_until' => $retention, 'report_profile' => 'PDF/A-2b',
            'historical_integrity_asserted' => false, 'fixture_only' => true, 'files' => [],
        ];
        foreach (['journal', 'balance-sheet', 'profit-and-loss'] as $report) {
            $path = 'reports/'.$report.'.pdf';
            if ($report !== 'journal') {
                copy($package.'/reports/journal.pdf', $package.'/'.$path);
            }
            $manifest['files'][] = ['path' => $path, 'size' => filesize($package.'/'.$path), 'sha256' => hash_file('sha256', $package.'/'.$path), 'role' => 'report'];
        }
        file_put_contents($package.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $storage = new S3ComplianceStorage;
        $verifier = new StandaloneArchiveVerifier;
        $timestamps = new Rfc3161TimestampAuthority($runner);
        $pipeline = new ProofPipeline(new OpenSslManifestSigner($runner), $timestamps, $storage, $verifier, $converter);
        $stages = [];
        $result = $pipeline->process($package, $retention, function (string $stage) use (&$stages): void {
            $stages[] = $stage;
        });
        $this->assertSame(['validated', 'sealed', 'deposited', 'verified'], $stages);
        $this->assertTrue($result['verification']['valid']);
        $this->assertNotEmpty($result['storage']['objects']);
        $signature = hash_file('sha256', $package.'/evidence/manifest.p7s');
        $retry = $pipeline->process($package, $retention);
        $this->assertSame($result['storage'], $retry['storage']);
        $this->assertSame($signature, hash_file('sha256', $package.'/evidence/manifest.p7s'));
        $inventory = $storage->inventory($manifest['organization_id']);
        $this->assertContains($manifest['package_id'], array_column($inventory, 'package_id'));

        $client = $this->client();
        $object = $result['storage']['objects'][0];
        $query = ['Bucket' => $this->settings['bucket'], 'Key' => $object['key'], 'VersionId' => $object['version_id']];
        foreach ([
            fn () => $client->deleteObject($query),
            fn () => $client->putObjectRetention([...$query, 'Retention' => ['Mode' => 'COMPLIANCE', 'RetainUntilDate' => now()->addHour()]]),
        ] as $forbidden) {
            try {
                $forbidden();
                $this->fail('Actual Compliance retention must reject deletion and shortening even by the test storage administrator.');
            } catch (AwsException $exception) {
                if ($exception->getStatusCode() === 403) {
                    $this->assertSame('AccessDenied', $exception->getAwsErrorCode());
                } else {
                    $this->assertSame(400, $exception->getStatusCode());
                    $this->assertSame('InvalidRequest', $exception->getAwsErrorCode());
                    $this->assertStringContainsString('WORM protected', $exception->getAwsErrorMessage());
                }
            }
        }
        $catalog = $result['storage']['catalog'];
        $client->deleteObject(['Bucket' => $this->settings['bucket'], 'Key' => $catalog['key']]);
        $this->assertContains($manifest['package_id'], array_column($storage->inventory($manifest['organization_id']), 'package_id'));
        $storage->verify($package, $result['storage']);

        $renewal = (new RenewArchiveEvidence($verifier, $timestamps, $storage))->renew($package, gmdate('Ymd\THis\Z').'-'.bin2hex(random_bytes(4)), $retention, $result['manifest_sha256']);
        $this->assertSame(1, $renewal['verification']['renewals_checked']);
        $this->assertSame($signature, hash_file('sha256', $package.'/evidence/manifest.p7s'));
        $protected = $storage->protect($renewal['storage'], now()->addDays(3)->startOfSecond()->toIso8601String(), true);
        $storage->verify($package, $protected);
        $this->assertSame('ON', $client->getObjectLegalHold([...$query])['LegalHold']['Status']);
        $this->assertSame('ON', $client->getObjectLegalHold(['Bucket' => $this->settings['bucket'], 'Key' => $catalog['key'], 'VersionId' => $catalog['version_id']])['LegalHold']['Status']);

        $recovered = $directory.'/independent-recovery';
        mkdir($recovered, 0700);
        foreach ($protected['objects'] as $saved) {
            $contents = (string) $client->getObject(['Bucket' => $this->settings['bucket'], 'Key' => $saved['key'], 'VersionId' => $saved['version_id']])['Body'];
            EvidenceFiles::writeOnce($recovered.'/'.$saved['path'], $contents);
        }
        $independentArguments = [PHP_BINARY, base_path('scripts/archive-verifier/verify.php'), '--directory='.$recovered, '--signer-trust='.config('archiving.signing.trust_store'), '--tsa-trust='.config('archiving.timestamp.trust_store'), '--signer-fingerprint='.config('archiving.signing.allowed_fingerprint'), '--expected-manifest-sha256='.$result['manifest_sha256'], '--verapdf='.config('archiving.pdfa.verapdf')];
        $independent = json_decode($runner->run($independentArguments), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($independent['valid']);
        $this->assertFalse($independent['storage_protection_checked']);
        foreach (['reports/journal.pdf', 'manifest.json', 'evidence/manifest.p7s', 'evidence/manifest.tsr'] as $path) {
            $copy = $directory.'/tampered-'.hash('sha256', $path);
            File::copyDirectory($recovered, $copy);
            file_put_contents($copy.'/'.$path, 'tampered');
            try {
                $verifier->verify($copy, $result['manifest_sha256']);
                $this->fail('Changed content or evidence must fail independent verification: '.$path);
            } catch (RuntimeException) {
                $this->assertTrue(true);
            }
        }
        $trusted = config('archiving.signing.trust_store');
        config(['archiving.signing.trust_store' => $this->lab.'/pki/tls/ca.pem']);
        try {
            $verifier->verify($recovered, $result['manifest_sha256']);
            $this->fail('An unrelated external CA must not establish signer trust.');
        } catch (RuntimeException) {
            $this->assertTrue(true);
        } finally {
            config(['archiving.signing.trust_store' => $trusted]);
        }
        file_put_contents($this->lab.'/tsa-unavailable', 'fixture outage');
        file_put_contents($directory.'/outage-input', 'immutable outage recovery input');
        try {
            $timestamps->timestamp($directory.'/outage-input', $directory.'/outage');
            $this->fail('An unavailable real TSA must not create successful proof.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('HTTP 503', $exception->getMessage());
            $this->assertFileDoesNotExist($directory.'/outage.tsr');
        } finally {
            unlink($this->lab.'/tsa-unavailable');
        }
        $queryHash = hash_file('sha256', $directory.'/outage.tsq');
        $timestamps->timestamp($directory.'/outage-input', $directory.'/outage');
        $this->assertSame($queryHash, hash_file('sha256', $directory.'/outage.tsq'));
        $report = ['status' => 'passed', 'profile' => 'synthetic-local-https-tsa-minio-compliance', 'platform' => PHP_OS_FAMILY.'/'.php_uname('m'), 'package_id' => $manifest['package_id'], 'manifest_sha256' => $result['manifest_sha256'], 'stages' => $stages, 'files_recovered' => count($protected['objects']), 'independent_verification' => $independent, 'checks' => ['tls_verified' => true, 'basic_authentication' => true, 'compliance_delete_denied' => true, 'retention_shortening_denied' => true, 'delete_marker_cannot_hide_catalog' => true, 'historical_catalog_hold' => true, 'renewal_preserved_original_signature' => true, 'changed_bytes_rejected' => true, 'unrelated_anchor_rejected' => true, 'tsa_outage_recovered_same_query' => true], 'limitations' => ['Synthetic test authorities only', 'No commercial provider validation', 'No legal certification'], 'completed_at' => now()->toIso8601String()];
        file_put_contents($this->lab.'/evidence/network-profile-'.$run.'.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    private function client(): S3Client
    {
        return new S3Client(['version' => '2006-03-01', 'region' => $this->settings['region'], 'endpoint' => 'https://127.0.0.1:19443', 'use_path_style_endpoint' => true, 'credentials' => ['key' => $this->settings['access_key'], 'secret' => $this->settings['secret_key']], 'http' => ['verify' => $this->lab.'/pki/tls/ca.pem', 'connect_timeout' => 10, 'timeout' => 30], 'retries' => 0]);
    }
}
