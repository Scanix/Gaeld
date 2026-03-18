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
    use CamtXmlHelper;
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
        $this->notificationId = $this->xpathText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Id");
        $this->creationDate = $this->xpathText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}CreDtTm");
        $this->iban = $this->xpathText($xpath, "//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Acct/{$prefix}Id/{$prefix}IBAN");

        $entries = $xpath->query("//{$prefix}BkToCstmrDbtCdtNtfctn/{$prefix}Ntfctn/{$prefix}Ntry");

        foreach ($entries as $entryNode) {
            $this->parseEntry($xpath, $entryNode, $prefix);
        }

        return $this;
    }

    private function parseEntry(\DOMXPath $xpath, \DOMElement $entryNode, string $prefix): void
    {
        $amount = $this->contextText($xpath, "{$prefix}Amt", $entryNode);
        $currency = $this->contextAttr($xpath, "{$prefix}Amt", $entryNode, 'Ccy');
        $creditDebitIndicator = $this->contextText($xpath, "{$prefix}CdtDbtInd", $entryNode);
        $bookingDate = $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}Dt", $entryNode)
            ?? $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}DtTm", $entryNode);
        $valueDate = $this->contextText($xpath, "{$prefix}ValDt/{$prefix}Dt", $entryNode)
            ?? $this->contextText($xpath, "{$prefix}ValDt/{$prefix}DtTm", $entryNode);

        if (! $amount || ! $creditDebitIndicator) {
            return;
        }

        $date = $bookingDate ?? $valueDate ?? date('Y-m-d');
        if (strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        $type = strtoupper($creditDebitIndicator) === 'CRDT' ? 'credit' : 'debit';

        $transactionDetails = $xpath->query("{$prefix}NtryDtls/{$prefix}TxDtls", $entryNode);

        if ($transactionDetails->length > 0) {
            foreach ($transactionDetails as $detail) {
                $this->entries[] = $this->parseTxDetail($xpath, $detail, $prefix, $date, $amount, $currency, $type);
            }
        } else {
            $ref = $this->contextText($xpath, "{$prefix}AcctSvcrRef", $entryNode)
                ?? $this->contextText($xpath, "{$prefix}NtryRef", $entryNode);
            $desc = $this->contextText($xpath, "{$prefix}AddtlNtryInf", $entryNode);

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

    private function parseTxDetail(\DOMXPath $xpath, \DOMElement $detail, string $prefix, string $date, string $fallbackAmount, ?string $fallbackCurrency, string $type): CamtEntry
    {
        $txAmount = $this->contextText($xpath, "{$prefix}Amt", $detail) ?? $fallbackAmount;
        $txCurrency = $this->contextAttr($xpath, "{$prefix}Amt", $detail, 'Ccy') ?? $fallbackCurrency ?? 'CHF';

        $endToEndId = $this->contextText($xpath, "{$prefix}Refs/{$prefix}EndToEndId", $detail);

        // Strip NOTPROVIDED end-to-end IDs
        if ($endToEndId && strtoupper($endToEndId) === 'NOTPROVIDED') {
            $endToEndId = null;
        }

        $ref = $endToEndId
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}AcctSvcrRef", $detail)
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}PmtInfId", $detail);

        $debtorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Nm", $detail)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Pty/{$prefix}Nm", $detail);

        $creditorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Nm", $detail)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Pty/{$prefix}Nm", $detail);

        $description = $this->contextText($xpath, "{$prefix}RmtInf/{$prefix}Ustrd", $detail)
            ?? $this->contextText($xpath, "{$prefix}AddtlTxInf", $detail);

        // Extract structured creditor reference (Swiss QR reference)
        $structuredReference = $this->contextText($xpath, "{$prefix}RmtInf/{$prefix}Strd/{$prefix}CdtrRefInf/{$prefix}Ref", $detail);

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

}
