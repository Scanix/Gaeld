<?php

namespace Tests\Unit\Invoicing;

use App\Domains\Invoicing\Support\QrBillValidationMessageFormatter;
use Tests\TestCase;

/**
 * QrBillValidationMessageFormatter previously had zero test coverage despite
 * translating raw library violation strings into the user-facing flash
 * message shown on failed invoice-send/QR-PDF attempts (see
 * InvoiceCommunicationController, InvoiceDocumentController). A
 * mis-categorized violation would show the wrong help text (or the generic
 * fallback) to a user trying to fix a real QR-bill configuration problem.
 */
class QrBillValidationMessageFormatterTest extends TestCase
{
    private QrBillValidationMessageFormatter $formatter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->formatter = new QrBillValidationMessageFormatter;
    }

    public function test_empty_violations_produce_the_generic_message(): void
    {
        $message = $this->formatter->format([]);

        $this->assertSame(__('app.qr_invoice_error_generic'), $message);
    }

    public function test_qr_iban_violation_appends_the_help_text(): void
    {
        $message = $this->formatter->format(['Invalid QR-IBAN structure.']);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_qr_iban'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
        $this->assertStringContainsString(__('app.qr_iban_help_where_to_find'), $message);
    }

    public function test_creditor_account_violation_is_categorized_as_creditor_not_qr_iban(): void
    {
        $message = $this->formatter->format(['Creditor account is invalid.']);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_creditor'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
        $this->assertStringNotContainsString(__('app.qr_iban_help_where_to_find'), $message);
    }

    public function test_debtor_address_violation_is_categorized_as_customer(): void
    {
        $message = $this->formatter->format(['Debtor address is missing city.']);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_customer'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
    }

    public function test_amount_violation_is_categorized_as_amount(): void
    {
        $message = $this->formatter->format(['paymentAmountInformation.amount: This value should be between 0 and 999999999.99.']);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_amount'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
    }

    public function test_reference_violation_is_categorized_as_reference(): void
    {
        $message = $this->formatter->format(['QRR reference checksum is invalid.']);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_reference'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
    }

    public function test_multiple_distinct_violations_are_deduplicated_into_one_category_list(): void
    {
        $message = $this->formatter->format([
            'Creditor IBAN is invalid.',
            'Creditor account is invalid.',
        ]);

        $expectedSummary = __('app.qr_invoice_error_summary', [
            'details' => __('app.qr_invoice_error_detail_creditor'),
        ]);

        $this->assertStringContainsString($expectedSummary, $message);
        // Only one "creditor" category should appear, not the phrase repeated per violation.
        $this->assertSame(1, substr_count($message, __('app.qr_invoice_error_detail_creditor')));
    }

    public function test_unrecognized_violation_falls_back_to_generic_summary_with_raw_details(): void
    {
        $message = $this->formatter->format(['Some completely unmapped library error.']);

        $this->assertStringContainsString(__('app.qr_invoice_error_generic'), $message);
        $this->assertStringContainsString('Some completely unmapped library error.', $message);
    }

    public function test_raw_violation_details_are_always_appended_for_precision(): void
    {
        $message = $this->formatter->format(['Creditor account is invalid.']);

        $this->assertStringContainsString(__('app.qr_invoice_error_details_label'), $message);
        $this->assertStringContainsString('Creditor account is invalid.', $message);
    }
}
