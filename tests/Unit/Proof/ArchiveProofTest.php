<?php

namespace Tests\Unit\Proof;

use App\Domains\Accounting\Proof\EvidenceFiles;
use App\Domains\Accounting\Proof\OpenSslManifestSigner;
use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\ProcessRunner;
use App\Domains\Accounting\Proof\Rfc3161TimestampAuthority;
use App\Domains\Accounting\Proof\StandaloneArchiveVerifier;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\ArchiveProofFixtures;
use Tests\TestCase;

class ArchiveProofTest extends TestCase
{
    use ArchiveProofFixtures;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/gaeld-proof-'.bin2hex(random_bytes(12));
        mkdir($this->directory, 0700);
        Http::preventStrayRequests();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_inventory_checks_content_without_asserting_protection(): void
    {
        $manifest = $this->fixtureManifest($this->directory.'/package');
        $this->assertSame($manifest, (new StandaloneArchiveVerifier)->verifier()->inspect($this->directory.'/package'));
    }

    public function test_inventory_rejects_changed_file_even_when_size_matches(): void
    {
        $this->fixtureManifest($this->directory.'/package');
        file_put_contents($this->directory.'/package/journal.pdf', str_repeat('x', filesize($this->directory.'/package/journal.pdf')));
        $this->expectException(RuntimeException::class);
        (new StandaloneArchiveVerifier)->verifier()->inspect($this->directory.'/package');
    }

    public function test_independent_manifest_pin_detects_local_hash_replacement(): void
    {
        $manifest = $this->fixtureManifest($this->directory.'/package');
        $hash = hash_file('sha256', $this->directory.'/package/manifest.json');
        file_put_contents($this->directory.'/package/journal.pdf', 'replaced');
        $manifest['files'][0]['sha256'] = hash_file('sha256', $this->directory.'/package/journal.pdf');
        $manifest['files'][0]['size'] = filesize($this->directory.'/package/journal.pdf');
        file_put_contents($this->directory.'/package/manifest.json', json_encode($manifest));
        $this->expectException(RuntimeException::class);
        (new StandaloneArchiveVerifier)->verifier()->inspect($this->directory.'/package', $hash);
    }

    public function test_inventory_rejects_path_traversal_and_symbolic_links(): void
    {
        $manifest = $this->fixtureManifest($this->directory.'/package');
        rename($this->directory.'/package/journal.pdf', $this->directory.'/outside.pdf');
        symlink($this->directory.'/outside.pdf', $this->directory.'/package/journal.pdf');
        $this->expectException(RuntimeException::class);
        (new StandaloneArchiveVerifier)->verifier()->inspect($this->directory.'/package');
    }

    public function test_evidence_cannot_be_replaced_with_different_bytes(): void
    {
        EvidenceFiles::writeOnce($this->directory.'/evidence.bin', 'first');
        EvidenceFiles::writeOnce($this->directory.'/evidence.bin', 'first');
        $this->expectException(RuntimeException::class);
        EvidenceFiles::writeOnce($this->directory.'/evidence.bin', 'second');
    }

    public function test_missing_signing_configuration_never_creates_a_signature(): void
    {
        $this->fixtureManifest($this->directory.'/package');
        $this->expectException(RuntimeException::class);
        (new OpenSslManifestSigner(new ProcessRunner))->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
    }

    public function test_real_cms_signature_is_detached_and_reuse_preserves_bytes(): void
    {
        $this->configureProofCertificates($this->directory);
        $this->fixtureManifest($this->directory.'/package');
        $signer = new OpenSslManifestSigner(new ProcessRunner);
        $result = $signer->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
        $this->assertSame('CMS-detached', $result['format']);
        $this->assertSame($result, $signer->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence'));
        file_put_contents($this->directory.'/package/manifest.json', '{}');
        $this->expectException(RuntimeException::class);
        $signer->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
    }

    public function test_revoked_signing_certificate_is_rejected(): void
    {
        $this->configureProofCertificates($this->directory);
        $this->fixtureManifest($this->directory.'/package');
        $runner = new ProcessRunner;
        $runner->run(['openssl', 'ca', '-config', $this->directory.'/certificates/ca.cnf', '-revoke', $this->directory.'/certificates/signer.pem']);
        $runner->run(['openssl', 'ca', '-gencrl', '-config', $this->directory.'/certificates/ca.cnf', '-out', $this->directory.'/certificates/ca.crl.pem']);
        $this->expectException(RuntimeException::class);
        (new OpenSslManifestSigner($runner))->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
    }

    public function test_expired_certificate_cannot_create_new_archive_evidence(): void
    {
        $this->configureProofCertificates($this->directory);
        $this->fixtureManifest($this->directory.'/package');
        $runner = new ProcessRunner;
        $certs = $this->directory.'/certificates';
        $runner->run(['openssl', 'ca', '-batch', '-config', $certs.'/ca.cnf', '-extensions', 'signer', '-in', $certs.'/signer.csr', '-out', $certs.'/expired.pem', '-startdate', '20000101000000Z', '-enddate', '20010101000000Z']);
        config(['archiving.signing.certificate' => $certs.'/expired.pem', 'archiving.signing.allowed_fingerprint' => openssl_x509_fingerprint(file_get_contents($certs.'/expired.pem'), 'sha256')]);
        $this->expectException(RuntimeException::class);
        (new OpenSslManifestSigner($runner))->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
    }

    public function test_timestamp_generation_time_preserves_utc_and_orders_fractions_within_one_second(): void
    {
        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $parse = new \ReflectionMethod($verifier, 'generationTime');
        $whole = $parse->invoke($verifier, "Version: 1\nTime stamp: Apr  7 04:03:02 2026 GMT");
        $early = $parse->invoke($verifier, "Version: 1\nTime stamp: Apr  7 04:03:02.000000005 2026 GMT");
        $late = $parse->invoke($verifier, "Version: 1\nTime stamp: Apr  7 04:03:02.5110 2026 GMT");

        $this->assertSame('2026-04-07T04:03:02+00:00', $whole['utc']);
        $this->assertSame('2026-04-07T04:03:02.000000005+00:00', $early['utc']);
        $this->assertSame('2026-04-07T04:03:02.511+00:00', $late['utc']);
        $this->assertSame($whole['second'], $early['second']);
        $this->assertTrue(strcmp($whole['utc'], $early['utc']) < 0 && strcmp($early['utc'], $late['utc']) < 0);

        $this->expectException(RuntimeException::class);
        $parse->invoke($verifier, "Version: 1\nTime stamp: Apr 31 04:03:02.511 2026 GMT");
    }

    public function test_generation_time_accepts_only_the_token_view_of_an_issued_response(): void
    {
        $this->configureProofCertificates($this->directory);
        $runner = new ProcessRunner;
        $source = $this->directory.'/document';
        $query = $this->directory.'/request.tsq';
        $response = $this->directory.'/response.tsr';
        file_put_contents($source, 'Timestamp qualification');
        $runner->run(['openssl', 'ts', '-query', '-data', $source, '-sha256', '-cert', '-out', $query]);
        $runner->run(['openssl', 'ts', '-reply', '-config', $this->directory.'/certificates/tsa.cnf', '-queryfile', $query, '-out', $response]);

        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $parse = new \ReflectionMethod($verifier, 'generationTime');
        $reply = ['openssl', 'ts', '-reply', '-in', $response, '-text'];
        $issued = $parse->invoke($verifier, $runner->run([...$reply, '-token_out']));
        $this->assertGreaterThan(0, $issued['second']);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no unambiguous generation time');
        $parse->invoke($verifier, $runner->run($reply));
    }

    public function test_generation_time_requires_a_timestamp_in_the_token_view(): void
    {
        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('no unambiguous generation time');
        (new \ReflectionMethod($verifier, 'generationTime'))->invoke($verifier, 'Version: 1');
    }

    public function test_timestamp_store_accepts_multiple_crls_and_rejects_unrelated_input(): void
    {
        $this->configureProofCertificates($this->directory);
        $list = file_get_contents($this->directory.'/certificates/ca.crl.pem');
        $input = $this->directory.'/revocations.pem';
        $output = $this->directory.'/decoded-crls.pem';
        $verifier = (new StandaloneArchiveVerifier)->verifier();

        file_put_contents($input, str_replace("\n", "\r\n", $list)."\n".$list);
        $verifier->writeTimestampRevocationStore($input, $output);
        $this->assertSame($list.$list, file_get_contents($output));

        file_put_contents($input, $list."fixture notes\n");
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only X.509 CRLs');
        $verifier->writeTimestampRevocationStore($input, $output);
    }

    public function test_a_certificate_is_not_valid_input_for_the_revocation_store(): void
    {
        $this->configureProofCertificates($this->directory);
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('only X.509 CRLs');

        (new StandaloneArchiveVerifier)->verifier()->writeTimestampRevocationStore(
            $this->directory.'/certificates/ca.pem',
            $this->directory.'/decoded-crls.pem',
        );
    }

    public function test_real_timestamp_binds_signature_and_is_reused_without_new_request(): void
    {
        $this->configureProofCertificates($this->directory);
        $this->fixtureManifest($this->directory.'/package');
        (new OpenSslManifestSigner(new ProcessRunner))->sign($this->directory.'/package/manifest.json', $this->directory.'/package/evidence');
        Http::fake(function ($request) {
            file_put_contents($this->directory.'/request.tsq', $request->body());
            $response = (new ProcessRunner)->run(['openssl', 'ts', '-reply', '-config', $this->directory.'/certificates/tsa.cnf', '-queryfile', $this->directory.'/request.tsq']);

            return Http::response($response, 200, ['Content-Type' => 'application/timestamp-reply']);
        });
        $authority = new Rfc3161TimestampAuthority(new ProcessRunner);
        $source = $this->directory.'/package/evidence/manifest.p7s';
        $prefix = $this->directory.'/package/evidence/manifest';
        $result = $authority->timestamp($source, $prefix);
        $this->assertSame($result, $authority->timestamp($source, $prefix));
        Http::assertSentCount(1);
        file_put_contents($source, 'changed-signature');
        $this->expectException(RuntimeException::class);
        $authority->timestamp($source, $prefix);
    }

    public function test_ordinary_pdf_and_incorrect_verapdf_profile_are_rejected(): void
    {
        $this->fixtureManifest($this->directory.'/package');
        $runner = $this->createMock(ProcessRunner::class);
        $runner->method('run')->willReturn('<report><validationReport isCompliant="true" profileName="PDF/A-1b validation profile"/></report>');
        $this->expectException(RuntimeException::class);
        (new PdfArchivalConverter($runner))->validate($this->directory.'/package/journal.pdf');
    }

    public function test_pdfa_validation_preserves_report_bytes_and_rejects_a_document_type(): void
    {
        $this->fixtureManifest($this->directory.'/package');
        $body = '<report><validationReport isCompliant="true" profileName="PDF/A-2b validation profile"/></report>';
        $encoded = "\xff\xfe".mb_convert_encoding('<?xml version="1.0" encoding="UTF-16"?>'.$body, 'UTF-16LE', 'UTF-8');
        $runner = $this->createMock(ProcessRunner::class);
        $runner->expects($this->exactly(2))->method('run')->willReturnOnConsecutiveCalls($encoded, '<!DOCTYPE report>'.$body);
        $converter = new PdfArchivalConverter($runner);
        $file = $this->directory.'/package/journal.pdf';
        $this->assertSame($encoded, $converter->validate($file));
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('veraPDF did not return a valid validation report.');
        $converter->validate($file);
    }

    public function test_standalone_cli_needs_no_laravel_or_ee(): void
    {
        $this->fixtureManifest($this->directory.'/package');
        $output = (new ProcessRunner)->run([PHP_BINARY, base_path('scripts/archive-verifier/verify.php'), '--directory='.$this->directory.'/package', '--integrity-only']);
        $result = json_decode($output, true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue($result['content_integrity']);
        $this->assertFalse($result['proof_profile_verified']);
    }

    public function test_full_profile_rejects_a_missing_required_financial_statement_before_signing(): void
    {
        $manifest = $this->fixtureManifest($this->directory.'/package');
        $manifest['report_profile'] = 'PDF/A-2b';
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('required annual accounting report');
        (new StandaloneArchiveVerifier)->verifier()->validateProfile($manifest);
    }

    public function test_standalone_integrity_verifier_reads_r2_content_packages_but_rejects_extra_evidence(): void
    {
        $directory = $this->directory.'/package';
        $manifest = $this->fixtureManifest($directory);
        $manifest['proof_profile'] = 'gaeld-content-package/1.0.0';
        $manifest['report_profile'] = 'PDF';
        file_put_contents($directory.'/manifest.json', json_encode($manifest, JSON_THROW_ON_ERROR));
        $verifier = (new StandaloneArchiveVerifier)->verifier();
        $this->assertSame($manifest, $verifier->inspect($directory));
        EvidenceFiles::writeOnce($directory.'/evidence/unrecorded.bin', 'unexpected');
        $this->expectException(RuntimeException::class);
        $verifier->inspect($directory);
    }

    public function test_timestamp_basic_authentication_uses_protected_files_and_verifies_the_real_response(): void
    {
        $this->configureProofCertificates($this->directory);
        file_put_contents($this->directory.'/username', "synthetic-user\n");
        file_put_contents($this->directory.'/password', "synthetic-secret\n");
        config(['archiving.timestamp.basic_username_file' => $this->directory.'/username', 'archiving.timestamp.basic_password_file' => $this->directory.'/password']);
        Http::fake(function ($request) {
            $this->assertTrue($request->hasHeader('Authorization', 'Basic '.base64_encode('synthetic-user:synthetic-secret')));
            file_put_contents($this->directory.'/request.tsq', $request->body());

            return Http::response((new ProcessRunner)->run(['openssl', 'ts', '-reply', '-config', $this->directory.'/certificates/tsa.cnf', '-queryfile', $this->directory.'/request.tsq']), 200, ['Content-Type' => 'application/timestamp-reply']);
        });
        file_put_contents($this->directory.'/source', 'synthetic timestamp input');
        $result = (new Rfc3161TimestampAuthority(new ProcessRunner))->timestamp($this->directory.'/source', $this->directory.'/timestamp');
        $this->assertSame('RFC3161', $result['protocol']);
        Http::assertSentCount(1);
    }

    public function test_timestamp_authentication_is_unambiguous_and_provider_errors_do_not_reveal_credentials(): void
    {
        $this->configureProofCertificates($this->directory);
        file_put_contents($this->directory.'/username', 'synthetic-user');
        file_put_contents($this->directory.'/password', 'synthetic-secret');
        file_put_contents($this->directory.'/source', 'synthetic timestamp input');
        config(['archiving.timestamp.basic_username_file' => $this->directory.'/username', 'archiving.timestamp.basic_password_file' => $this->directory.'/password', 'archiving.timestamp.bearer_token' => 'conflicting-token']);
        $authority = new Rfc3161TimestampAuthority(new ProcessRunner);
        try {
            $authority->timestamp($this->directory.'/source', $this->directory.'/timestamp');
            $this->fail('Conflicting authentication methods must fail before sending.');
        } catch (RuntimeException $exception) {
            $this->assertStringContainsString('exactly one', $exception->getMessage());
            Http::assertNothingSent();
        }
        config(['archiving.timestamp.bearer_token' => null]);
        Http::fake(['*' => Http::response('Rejected synthetic-user synthetic-secret', 401)]);
        try {
            $authority->timestamp($this->directory.'/source', $this->directory.'/timestamp');
            $this->fail('A rejected authority request must not create proof.');
        } catch (RuntimeException $exception) {
            $this->assertSame('The timestamp authority rejected the request (HTTP 401).', $exception->getMessage());
            $this->assertNull($exception->getPrevious());
            $this->assertFileDoesNotExist($this->directory.'/timestamp.tsr');
        }
    }
}
