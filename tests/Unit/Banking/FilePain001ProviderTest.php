<?php

namespace Tests\Unit\Banking;

use App\Domains\Banking\DTOs\PaymentInstructionData;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\Payments\FilePain001Provider;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Support\Carbon;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class FilePain001ProviderTest extends TestCase
{
    private function debtor(): BankAccount
    {
        $org = new Organization(['legal_name' => 'Acme SA', 'name' => 'Acme']);
        $ba = new BankAccount([
            'name' => 'Main',
            'iban' => 'CH9300762011623852957',
            'currency' => 'CHF',
        ]);
        $ba->setRelation('organization', $org);

        return $ba;
    }

    private function instruction(string $endToEndId, string $amount, string $currency = 'CHF', ?string $structuredRef = null): PaymentInstructionData
    {
        return new PaymentInstructionData(
            endToEndId: $endToEndId,
            creditorName: 'Supplier AG',
            creditorIban: 'CH4431999123000889012',
            amount: $amount,
            currency: $currency,
            executionDate: Carbon::parse('2026-04-01'),
            structuredReference: $structuredRef,
            unstructuredRemittance: $structuredRef ? null : 'Invoice #42',
            sourceType: 'expense',
            sourceId: 'exp-1',
        );
    }

    public function test_builds_valid_pain001_with_qrr_reference(): void
    {
        $provider = new FilePain001Provider;
        $result = $provider->initiate($this->debtor(), [
            $this->instruction('GAELD-1', '120.00', 'CHF', '210000000003139471430009017'),
        ]);

        $this->assertTrue($result->isFile());
        $this->assertEquals(1, $result->count);
        $this->assertEquals('120.00', $result->totalAmount);
        $this->assertInstanceOf(Response::class, $result->download);

        $xml = (string) $result->download->getContent();
        $this->assertStringContainsString('urn:iso:std:iso:20022:tech:xsd:pain.001.001.09', $xml);
        $this->assertStringContainsString('<NbOfTxs>1</NbOfTxs>', $xml);
        $this->assertStringContainsString('<CtrlSum>120.00</CtrlSum>', $xml);
        $this->assertStringContainsString('<Prtry>QRR</Prtry>', $xml);
        $this->assertStringContainsString('210000000003139471430009017', $xml);
        $this->assertStringContainsString('<EndToEndId>GAELD-1</EndToEndId>', $xml);
    }

    public function test_uses_scor_for_iso_creditor_reference(): void
    {
        $provider = new FilePain001Provider;
        $result = $provider->initiate($this->debtor(), [
            $this->instruction('GAELD-2', '50.00', 'CHF', 'RF18539007547034'),
        ]);

        $xml = (string) $result->download->getContent();
        $this->assertStringContainsString('<Cd>SCOR</Cd>', $xml);
        $this->assertStringContainsString('RF18539007547034', $xml);
    }

    public function test_uses_unstructured_remittance_when_no_reference(): void
    {
        $provider = new FilePain001Provider;
        $result = $provider->initiate($this->debtor(), [
            $this->instruction('GAELD-3', '75.00'),
        ]);

        $xml = (string) $result->download->getContent();
        $this->assertStringContainsString('<Ustrd>Invoice #42</Ustrd>', $xml);
        $this->assertStringNotContainsString('<Prtry>QRR</Prtry>', $xml);
    }

    public function test_aggregates_total_amount(): void
    {
        $provider = new FilePain001Provider;
        $result = $provider->initiate($this->debtor(), [
            $this->instruction('GAELD-A', '100.00'),
            $this->instruction('GAELD-B', '250.50'),
        ]);

        $this->assertEquals(2, $result->count);
        $this->assertEquals('350.50', $result->totalAmount);
        $xml = (string) $result->download->getContent();
        $this->assertStringContainsString('<NbOfTxs>2</NbOfTxs>', $xml);
        $this->assertStringContainsString('<CtrlSum>350.50</CtrlSum>', $xml);
    }

    public function test_throws_on_empty_batch(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FilePain001Provider)->initiate($this->debtor(), []);
    }

    public function test_throws_on_mixed_currencies(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new FilePain001Provider)->initiate($this->debtor(), [
            $this->instruction('A', '10.00', 'CHF'),
            $this->instruction('B', '10.00', 'EUR'),
        ]);
    }

    public function test_throws_when_debtor_has_no_iban(): void
    {
        $org = new Organization(['legal_name' => 'Acme', 'name' => 'Acme']);
        $ba = new BankAccount(['name' => 'Cash', 'iban' => null, 'currency' => 'CHF']);
        $ba->setRelation('organization', $org);

        $this->expectException(\InvalidArgumentException::class);
        (new FilePain001Provider)->initiate($ba, [$this->instruction('A', '10.00')]);
    }
}
