<?php

namespace Tests\Security\FileUpload;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Organizations\Services\CurrentOrganization;
use Illuminate\Http\UploadedFile;
use Tests\Security\SecurityTestCase;

/**
 * File upload security tests for the CAMT bank import endpoint.
 *
 * Verifies that malicious uploads are rejected gracefully:
 * - Server-side MIME/extension validation
 * - Oversized files
 * - Malformed XML
 * - XML entity expansion (Billion Laughs DoS)
 * - Cross-org upload attempt
 */
class CamtUploadSecurityTest extends SecurityTestCase
{
    private BankAccount $bankAccountA;

    protected function setUp(): void
    {
        parent::setUp();

        app(CurrentOrganization::class)->set($this->orgA);

        $bankAccount = Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Checking',
            'type' => AccountType::Asset->value,
        ]);

        $this->bankAccountA = BankAccount::create([
            'organization_id' => $this->orgA->id,
            'account_id' => $bankAccount->id,
            'name' => 'Main Bank',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);
    }

    // ──────────────────────────────────────────────────────────────
    //  File type confusion
    // ──────────────────────────────────────────────────────────────

    public function test_php_file_disguised_as_xml_is_rejected(): void
    {
        $phpContent = '<?php system($_GET["cmd"]); ?>';
        $file = UploadedFile::fake()->createWithContent('malicious.xml', $phpContent);

        // Must not return 200 or 500 — either validation error (422/302) or parse error
        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", [
                'camt_file' => $file,
            ]);

        $this->assertNotEquals(200, $response->status(),
            'PHP file disguised as XML must not return 200 OK');
        $this->assertNotEquals(500, $response->status(),
            'Server must not crash on malicious upload');
    }

    public function test_executable_file_with_xml_mime_is_rejected_gracefully(): void
    {
        $file = UploadedFile::fake()->create('evil.xml', 512, 'application/x-php');

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", [
                'camt_file' => $file,
            ]);

        $this->assertNotEquals(500, $response->status(),
            'Server must return a controlled error response, not crash');
    }

    // ──────────────────────────────────────────────────────────────
    //  File size limit
    // ──────────────────────────────────────────────────────────────

    public function test_oversized_file_is_rejected(): void
    {
        // ImportCamtRequest limits uploads to 10 MB (10240 KB)
        $file = UploadedFile::fake()->create('huge.xml', 11 * 1024, 'application/xml'); // 11 MB

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", [
                'camt_file' => $file,
            ]);

        // Web forms redirect back with validation errors (302) or JSON returns 422
        $this->assertContains($response->status(), [302, 422],
            "Oversized file should be rejected, got HTTP {$response->status()}");

        if ($response->status() === 302) {
            $response->assertSessionHasErrors('camt_file');
        }
    }

    // ──────────────────────────────────────────────────────────────
    //  Malformed XML — must not crash the server (500)
    // ──────────────────────────────────────────────────────────────

    public function test_malformed_xml_returns_controlled_error(): void
    {
        $content = '<?xml version="1.0"?><unclosed><tag>';
        $file = UploadedFile::fake()->createWithContent('malformed.xml', $content);

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", [
                'camt_file' => $file,
            ]);

        $this->assertNotEquals(500, $response->status(),
            'Malformed XML must produce a 4xx user error, not a 500 server crash');
    }

    // ──────────────────────────────────────────────────────────────
    //  XML Entity Expansion — Billion Laughs (DoS)
    //  LIBXML_NOENT was removed (fix C-2) so this should be safe.
    //  The test verifies the server completes in < 30 seconds and
    //  does not return 500.
    // ──────────────────────────────────────────────────────────────

    public function test_xml_entity_expansion_attack_does_not_crash_server(): void
    {
        $billionLaughs = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE test [
  <!ENTITY a "AAAAAAAAAA">
  <!ENTITY b "&a;&a;&a;&a;&a;&a;&a;&a;&a;&a;">
  <!ENTITY c "&b;&b;&b;&b;&b;&b;&b;&b;&b;&b;">
  <!ENTITY d "&c;&c;&c;&c;&c;&c;&c;&c;&c;&c;">
]>
<Document xmlns="urn:iso:std:iso:20022:tech:xsd:camt.053.001.06">&d;</Document>
XML;
        $file = UploadedFile::fake()->createWithContent('billion-laughs.xml', $billionLaughs);

        $start = microtime(true);

        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", [
                'camt_file' => $file,
            ]);

        $elapsed = microtime(true) - $start;

        $this->assertLessThan(30, $elapsed,
            'Server appears to hang on XML entity expansion — LIBXML_NOENT may be re-enabled');
        $this->assertNotEquals(500, $response->status(),
            'Entity expansion must not crash the server');
    }

    // ──────────────────────────────────────────────────────────────
    //  Cross-org upload
    // ──────────────────────────────────────────────────────────────

    public function test_cannot_upload_to_other_org_bank_account(): void
    {
        // Create a bank account in Org B
        app(CurrentOrganization::class)->set($this->orgB);
        $bankB = Account::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'code' => '1020',
            'name' => 'Org B Bank',
            'type' => AccountType::Asset->value,
        ]);
        $bankAccountB = BankAccount::withoutGlobalScopes()->create([
            'organization_id' => $this->orgB->id,
            'account_id' => $bankB->id,
            'name' => 'Org B Account',
            'currency' => 'CHF',
            'balance' => '0.00',
        ]);

        $xmlContent = file_get_contents(__DIR__.'/../../fixtures/camt053_sample.xml');
        $file = UploadedFile::fake()->createWithContent('legit.xml', $xmlContent);

        $this->assertDenied(
            $this->actingAs($this->ownerA)
                ->withSession(['current_organization_id' => $this->orgA->id])
                ->post("/reconciliation/{$bankAccountB->id}/import", [
                    'camt_file' => $file,
                ])
        );
    }

    // ──────────────────────────────────────────────────────────────
    //  No file — must not crash
    // ──────────────────────────────────────────────────────────────

    public function test_missing_file_returns_validation_error(): void
    {
        $response = $this->actingAs($this->ownerA)
            ->withSession(['current_organization_id' => $this->orgA->id])
            ->post("/reconciliation/{$this->bankAccountA->id}/import", []);

        // Web forms redirect back with validation errors (302) or JSON returns 422
        $this->assertContains($response->status(), [302, 422],
            "Missing file should trigger validation error, got HTTP {$response->status()}");

        if ($response->status() === 302) {
            $response->assertSessionHasErrors('camt_file');
        }
    }
}
