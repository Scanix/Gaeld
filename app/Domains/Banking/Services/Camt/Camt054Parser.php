<?php

namespace App\Domains\Banking\Services\Camt;

/**
 * Parser for CAMT.054 (Bank-to-Customer Debit/Credit Notification) XML files.
 *
 * CAMT.054 files contain debit/credit notifications rather than full
 * statements. They share a similar structure with CAMT.053 but use
 * BkToCstmrDbtCdtNtfctn as the root document element.
 *
 * Supports ISO 20022 camt.054.001.02 through .08.
 */
class Camt054Parser
{
    /** @var CamtEntry[] */
    private array $entries = [];

    private ?string $iban = null;

    private ?string $notificationId = null;

    private ?string $creationDate = null;

    /**
     * Parse a CAMT.054 XML string.
     *
     * @param  string  $xml  Raw XML content of the CAMT.054 file
     * @return self
     *
     * @throws \InvalidArgumentException  When the XML is invalid or not a CAMT.054
     */
    public function parse(string $xml): self
    {
        $this->entries = [];

        $doc = $this->loadXml($xml);
        $xpath = new \DOMXPath($doc);

        $ns = $doc->documentElement->lookupNamespaceURI(null) ?? '';
        if ($ns) {
            $xpath->registerNamespace('c', $ns);
            $prefix = 'c:';
        } else {
            $prefix = '';
        }

        // Validate root element
        $root = $xpath->query("//{$prefix}BkToCstmrDbtCdtNtfctn");
        if ($root->length === 0) {
            throw new \InvalidArgumentException('Not a valid CAMT.054 file: BkToCstmrDbtCdtNtfctn element not found.');
        }

        // Notification-level metadata
        $this->notificationId = $this->nodeText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Id");
        $this->creationDate = $this->nodeText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}CreDtTm");
        $this->iban = $this->nodeText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Acct/{$prefix}Id/{$prefix}IBAN");

        $entries = $xpath->query("//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Ntry");

        foreach ($entries as $ntry) {
            $this->parseEntry($xpath, $ntry, $prefix);
        }

        return $this;
    }

    private function parseEntry(\DOMXPath $xpath, \DOMElement $ntry, string $prefix): void
    {
        $amount = $this->contextText($xpath, "{$prefix}Amt", $ntry);
        $currency = $this->contextAttr($xpath, "{$prefix}Amt", $ntry, 'Ccy');
        $cdtDbtInd = $this->contextText($xpath, "{$prefix}CdtDbtInd", $ntry);
        $bookingDate = $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}Dt", $ntry)
            ?? $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}DtTm", $ntry);
        $valueDate = $this->contextText($xpath, "{$prefix}ValDt/{$prefix}Dt", $ntry)
            ?? $this->contextText($xpath, "{$prefix}ValDt/{$prefix}DtTm", $ntry);

        if (! $amount || ! $cdtDbtInd) {
            return;
        }

        $date = $bookingDate ?? $valueDate ?? date('Y-m-d');
        if (strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        $type = strtoupper($cdtDbtInd) === 'CRDT' ? 'credit' : 'debit';

        $txDtls = $xpath->query("{$prefix}NtryDtls/{$prefix}TxDtls", $ntry);

        if ($txDtls->length > 0) {
            foreach ($txDtls as $tx) {
                $this->entries[] = $this->parseTxDetail($xpath, $tx, $prefix, $date, $amount, $currency, $type);
            }
        } else {
            $ref = $this->contextText($xpath, "{$prefix}AcctSvcrRef", $ntry)
                ?? $this->contextText($xpath, "{$prefix}NtryRef", $ntry);
            $desc = $this->contextText($xpath, "{$prefix}AddtlNtryInf", $ntry);

            $this->entries[] = new CamtEntry(
                date: $date,
                amount: $amount,
                currency: $currency ?? 'CHF',
                type: $type,
                reference: $ref,
                description: $desc,
                iban: $this->iban,
                debtorName: null,
                creditorName: null,
                endToEndId: null,
            );
        }
    }

    private function parseTxDetail(\DOMXPath $xpath, \DOMElement $tx, string $prefix, string $date, string $fallbackAmount, ?string $fallbackCurrency, string $type): CamtEntry
    {
        $txAmount = $this->contextText($xpath, "{$prefix}Amt", $tx) ?? $fallbackAmount;
        $txCurrency = $this->contextAttr($xpath, "{$prefix}Amt", $tx, 'Ccy') ?? $fallbackCurrency ?? 'CHF';

        $endToEndId = $this->contextText($xpath, "{$prefix}Refs/{$prefix}EndToEndId", $tx);

        // Strip NOTPROVIDED end-to-end IDs
        if ($endToEndId && strtoupper($endToEndId) === 'NOTPROVIDED') {
            $endToEndId = null;
        }

        $ref = $endToEndId
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}AcctSvcrRef", $tx)
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}PmtInfId", $tx);

        $debtorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Nm", $tx)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Pty/{$prefix}Nm", $tx);

        $creditorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Nm", $tx)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Pty/{$prefix}Nm", $tx);

        $description = $this->contextText($xpath, "{$prefix}RmtInf/{$prefix}Ustrd", $tx)
            ?? $this->contextText($xpath, "{$prefix}AddtlTxInf", $tx);

        // Extract structured creditor reference (Swiss QR reference)
        $structuredReference = $this->contextText($xpath, "{$prefix}RmtInf/{$prefix}Strd/{$prefix}CdtrRefInf/{$prefix}Ref", $tx);

        // Normalize: strip whitespace from structured references
        if ($structuredReference) {
            $structuredReference = preg_replace('/\s+/', '', $structuredReference);
        }

        // Also try to extract QR reference from unstructured info if not found in structured
        if (! $structuredReference && $description) {
            $structuredReference = $this->extractQrReferenceFromText($description);
        }

        return new CamtEntry(
            date: $date,
            amount: $txAmount,
            currency: $txCurrency,
            type: $type,
            reference: $ref,
            description: $description,
            iban: $this->iban,
            debtorName: $debtorName,
            creditorName: $creditorName,
            endToEndId: $endToEndId,
            structuredReference: $structuredReference,
        );
    }

    /** @return CamtEntry[] */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getIban(): ?string
    {
        return $this->iban;
    }

    public function getNotificationId(): ?string
    {
        return $this->notificationId;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }

    // ──────────────────────────────────────────────────────────────
    //  XML Helpers
    // ──────────────────────────────────────────────────────────────

    /**
     * Try to extract a Swiss QR reference (27-digit) from unstructured text.
     */
    private function extractQrReferenceFromText(string $text): ?string
    {
        // Swiss QR reference: exactly 27 digits (may have spaces)
        $cleaned = preg_replace('/\s+/', '', $text);

        if (preg_match('/(?<!\d)(\d{27})(?!\d)/', $cleaned, $matches)) {
            return $matches[1];
        }

        return null;
    }

    private function loadXml(string $xml): \DOMDocument
    {
        $previousUseErrors = libxml_use_internal_errors(true);
        $doc = new \DOMDocument();

        if (! $doc->loadXML($xml, LIBXML_NONET | LIBXML_NOENT)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            $msg = ! empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new \InvalidArgumentException('Invalid XML: ' . trim($msg));
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        return $doc;
    }

    private function nodeText(\DOMXPath $xpath, string $query): ?string
    {
        $nodes = $xpath->query($query);

        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function contextText(\DOMXPath $xpath, string $query, \DOMElement $context): ?string
    {
        $nodes = $xpath->query($query, $context);

        return $nodes->length > 0 ? trim($nodes->item(0)->textContent) : null;
    }

    private function contextAttr(\DOMXPath $xpath, string $query, \DOMElement $context, string $attr): ?string
    {
        $nodes = $xpath->query($query, $context);

        if ($nodes->length === 0) {
            return null;
        }

        $node = $nodes->item(0);

        return ($node instanceof \DOMElement) ? ($node->getAttribute($attr) ?: null) : null;
    }
}
