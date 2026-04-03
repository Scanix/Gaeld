<?php

namespace Tests\Unit;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Parsers\Camt\Camt053Parser;
use App\Domains\Banking\Parsers\Camt\Camt054Parser;
use Tests\TestCase;

class CamtParserTest extends TestCase
{
    // ──────────────────────────────────────────────────────────────
    //  CAMT.053 Tests
    // ──────────────────────────────────────────────────────────────

    public function test_camt053_parses_statement_metadata(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt053_sample.xml');

        $parser = new Camt053Parser;
        $parser->parse($xml);

        $this->assertEquals('STMT-2026-001', $parser->getStatementId());
        $this->assertEquals('CH93 0076 2011 6238 5295 7', $parser->getIban());
        $this->assertNotNull($parser->getCreationDate());
    }

    public function test_camt053_parses_credit_entry_with_details(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt053_sample.xml');

        $parser = new Camt053Parser;
        $parser->parse($xml);

        $entries = $parser->getEntries();
        $this->assertGreaterThanOrEqual(3, count($entries));

        // First entry: credit with TxDtls
        $credit = $entries[0];
        $this->assertEquals('2026-03-10', $credit->date);
        $this->assertEquals('5000.00', $credit->amount);
        $this->assertEquals('CHF', $credit->currency);
        $this->assertEquals(BankTransactionType::Credit, $credit->type);
        $this->assertEquals('INV-2026-001', $credit->endToEndId);
        $this->assertEquals('INV-2026-001', $credit->reference);
        $this->assertEquals('Acme AG', $credit->debtorName);
        $this->assertEquals('Payment for invoice INV-2026-001', $credit->description);
    }

    public function test_camt053_parses_debit_entry(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt053_sample.xml');

        $parser = new Camt053Parser;
        $parser->parse($xml);

        $entries = $parser->getEntries();
        $debit = $entries[1];

        $this->assertEquals('2026-03-12', $debit->date);
        $this->assertEquals('200.00', $debit->amount);
        $this->assertEquals(BankTransactionType::Debit, $debit->type);
        $this->assertEquals('GitHub Inc', $debit->creditorName);
        $this->assertEquals('GitHub Pro subscription', $debit->description);
    }

    public function test_camt053_parses_entry_without_tx_details(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt053_sample.xml');

        $parser = new Camt053Parser;
        $parser->parse($xml);

        $entries = $parser->getEntries();
        $simple = $entries[2];

        $this->assertEquals('2026-03-14', $simple->date);
        $this->assertEquals('1500.00', $simple->amount);
        $this->assertEquals(BankTransactionType::Credit, $simple->type);
        $this->assertEquals('REF-003', $simple->reference);
        $this->assertEquals('Wire transfer from client', $simple->description);
    }

    public function test_camt053_rejects_invalid_xml(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $parser = new Camt053Parser;
        $parser->parse('not xml at all');
    }

    public function test_camt053_rejects_non_camt053_xml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a valid CAMT.053');

        $xml = '<?xml version="1.0"?><root><data>test</data></root>';

        $parser = new Camt053Parser;
        $parser->parse($xml);
    }

    // ──────────────────────────────────────────────────────────────
    //  CAMT.054 Tests
    // ──────────────────────────────────────────────────────────────

    public function test_camt054_parses_notification_metadata(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt054_sample.xml');

        $parser = new Camt054Parser;
        $parser->parse($xml);

        $this->assertEquals('NOTIF-2026-001', $parser->getNotificationId());
        $this->assertEquals('CH93 0076 2011 6238 5295 7', $parser->getIban());
        $this->assertNotNull($parser->getCreationDate());
    }

    public function test_camt054_parses_credit_notification(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt054_sample.xml');

        $parser = new Camt054Parser;
        $parser->parse($xml);

        $entries = $parser->getEntries();
        $this->assertCount(2, $entries);

        $credit = $entries[0];
        $this->assertEquals('2026-03-15', $credit->date);
        $this->assertEquals('3200.50', $credit->amount);
        $this->assertEquals(BankTransactionType::Credit, $credit->type);
        $this->assertEquals('INV-2026-005', $credit->endToEndId);
        $this->assertEquals('Swiss Corp SA', $credit->debtorName);
    }

    public function test_camt054_strips_notprovided_end_to_end_id(): void
    {
        $xml = file_get_contents(__DIR__.'/../fixtures/camt054_sample.xml');

        $parser = new Camt054Parser;
        $parser->parse($xml);

        $entries = $parser->getEntries();
        $debit = $entries[1];

        $this->assertNull($debit->endToEndId);
        $this->assertEquals('PMT-INFO-001', $debit->reference);
        $this->assertEquals(BankTransactionType::Debit, $debit->type);
        $this->assertEquals('89.90', $debit->amount);
        $this->assertEquals('Office Depot', $debit->creditorName);
    }

    public function test_camt054_rejects_non_camt054_xml(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Not a valid CAMT.054');

        $xml = '<?xml version="1.0"?><root><data>test</data></root>';

        $parser = new Camt054Parser;
        $parser->parse($xml);
    }
}
