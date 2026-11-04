<?php

namespace Tests\Unit\Proof;

use App\Domains\Accounting\Proof\Contracts\ProtectedArchiveStorage;
use App\Domains\Accounting\Proof\OpenSslManifestSigner;
use App\Domains\Accounting\Proof\PdfArchivalConverter;
use App\Domains\Accounting\Proof\ProcessRunner;
use App\Domains\Accounting\Proof\ProofPipeline;
use App\Domains\Accounting\Proof\RenewArchiveEvidence;
use App\Domains\Accounting\Proof\Rfc3161TimestampAuthority;
use App\Domains\Accounting\Proof\StandaloneArchiveVerifier;
use Dompdf\Dompdf;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\ArchiveProofFixtures;
use Tests\TestCase;

class ProofProfileIntegrationTest extends TestCase
{
    use ArchiveProofFixtures;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $icc = config('archiving.pdfa.icc_profile');
        if (! is_file($icc) && is_file('/usr/share/ghostscript/iccprofiles/srgb.icc')) {
            config(['archiving.pdfa.icc_profile' => '/usr/share/ghostscript/iccprofiles/srgb.icc']);
        }
        if (! is_executable(config('archiving.pdfa.verapdf')) || ! is_file(config('archiving.pdfa.icc_profile'))) {
            $this->markTestSkipped('R3 profile tests require installed Ghostscript, veraPDF and the sRGB ICC profile. The R3 release gate must supply them.');
        }
        $this->directory = sys_get_temp_dir().'/gaeld-profile-'.bin2hex(random_bytes(12));
        mkdir($this->directory, 0700);
        $this->configureProofCertificates($this->directory);
        Http::preventStrayRequests();
        Http::fake(function ($request) {
            file_put_contents($this->directory.'/request.tsq', $request->body());
            $response = (new ProcessRunner)->run(['openssl', 'ts', '-reply', '-config', $this->directory.'/certificates/tsa.cnf', '-queryfile', $this->directory.'/request.tsq']);

            return Http::response($response, 200, ['Content-Type' => 'application/timestamp-reply']);
        });
    }

    protected function tearDown(): void
    {
        if (isset($this->directory)) {
            File::deleteDirectory($this->directory);
        }
        parent::tearDown();
    }

    public function test_real_pdfa_conversion_cms_timestamp_independent_verification_and_renewal(): void
    {
        $package = $this->directory.'/package';
        $manifest = $this->fixtureManifest($package);
        unlink($package.'/journal.pdf');
        mkdir($package.'/reports');
        $pdf = new Dompdf;
        $pdf->loadHtml('<h1>Journal 2026–2027</h1><p>CHF 100,000.00 share capital</p>');
        $pdf->render();
        file_put_contents($this->directory.'/source.pdf', $pdf->output());
        $sourceHash = hash_file('sha256', $this->directory.'/source.pdf');
        $runner = new ProcessRunner;
        $converter = new PdfArchivalConverter($runner);
        $conversion = $converter->convert($this->directory.'/source.pdf', $package.'/reports/journal.pdf');
        $this->assertTrue($conversion['valid']);
        $this->assertSame($sourceHash, hash_file('sha256', $this->directory.'/source.pdf'));
        $manifest['files'] = [];
        $manifest['report_profile'] = 'PDF/A-2b';
        foreach (['journal', 'balance-sheet', 'profit-and-loss'] as $name) {
            $path = 'reports/'.$name.'.pdf';
            if ($name !== 'journal') {
                copy($package.'/reports/journal.pdf', $package.'/'.$path);
            }
            $manifest['files'][] = ['path' => $path, 'role' => 'report', 'size' => filesize($package.'/'.$path), 'sha256' => hash_file('sha256', $package.'/'.$path)];
        }
        file_put_contents($package.'/manifest.json', json_encode($manifest));
        $storage = $this->createMock(ProtectedArchiveStorage::class);
        $storage->method('deposit')->willReturn(['objects' => [], 'fixture_provider' => true]);
        $verifier = new StandaloneArchiveVerifier;
        $authority = new Rfc3161TimestampAuthority($runner);
        $stages = [];
        $pipeline = new ProofPipeline(new OpenSslManifestSigner($runner), $authority, $storage, $verifier, $converter);
        $result = $pipeline->process($package, $manifest['retention_until'], function (string $stage) use (&$stages): void {
            $stages[] = $stage;
        });
        $this->assertSame(['validated', 'sealed', 'deposited', 'verified'], $stages);
        $this->assertTrue($result['verification']['valid']);
        $this->assertTrue($result['verification']['inventory_pinned']);
        $signature = file_get_contents($package.'/evidence/manifest.p7s');
        $pipeline->process($package, $manifest['retention_until']);
        $this->assertSame($signature, file_get_contents($package.'/evidence/manifest.p7s'));
        Http::assertSentCount(1);
        $output = $runner->run([PHP_BINARY, base_path('scripts/archive-verifier/verify.php'), '--directory='.$package, '--signer-trust='.config('archiving.signing.trust_store'), '--tsa-trust='.config('archiving.timestamp.trust_store'), '--signer-fingerprint='.config('archiving.signing.allowed_fingerprint'), '--expected-manifest-sha256='.$result['manifest_sha256'], '--verapdf='.config('archiving.pdfa.verapdf')]);
        $this->assertTrue(json_decode($output, true)['valid']);
        $renewal = (new RenewArchiveEvidence($verifier, $authority, $storage))->renew($package, gmdate('Ymd\THis\Z').'-aabbccdd', $manifest['retention_until'], $result['manifest_sha256']);
        $this->assertSame(1, $renewal['verification']['renewals_checked']);
        $this->assertSame($signature, file_get_contents($package.'/evidence/manifest.p7s'));
    }

    public function test_invalid_timestamp_fails_closed_with_existing_content_readable(): void
    {
        $package = $this->directory.'/package';
        $this->fixtureManifest($package);
        (new OpenSslManifestSigner(new ProcessRunner))->sign($package.'/manifest.json', $package.'/evidence');
        Http::swap(new Factory);
        Http::fake(['https://tsa.example.test' => Http::response('invalid-response', 200, ['Content-Type' => 'application/timestamp-reply'])]);
        try {
            (new Rfc3161TimestampAuthority(new ProcessRunner))->timestamp($package.'/evidence/manifest.p7s', $package.'/evidence/manifest');
            $this->fail('An invalid timestamp must be rejected.');
        } catch (RuntimeException) {
            $this->assertFileDoesNotExist($package.'/evidence/manifest.tsr');
            $this->assertFileExists($package.'/journal.pdf');
        }
    }
}
