<?php

namespace App\Domains\Banking\Services\Camt;

use App\Domains\Banking\Enums\BankTransactionType;
use Illuminate\Support\Facades\Log;

trait CamtXmlHelper
{
    /**
     * Parse an individual bank statement entry (<Ntry> element).
     *
     * Extracts amounts, dates, and transaction details from the entry.
     * When transaction details are present, each is parsed separately.
     */
    private function parseEntry(\DOMXPath $xpath, \DOMElement $entryNode, string $prefix): void
    {
        // Stage 1: Extract entry-level amounts and indicators
        $amount = $this->contextText($xpath, "{$prefix}Amt", $entryNode);
        $currency = $this->contextAttr($xpath, "{$prefix}Amt", $entryNode, 'Ccy');
        $creditDebitIndicator = $this->contextText($xpath, "{$prefix}CdtDbtInd", $entryNode);

        // Stage 2: Resolve the effective date (booking > value > today)
        $bookingDate = $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}Dt", $entryNode)
            ?? $this->contextText($xpath, "{$prefix}BookgDt/{$prefix}DtTm", $entryNode);
        $valueDate = $this->contextText($xpath, "{$prefix}ValDt/{$prefix}Dt", $entryNode)
            ?? $this->contextText($xpath, "{$prefix}ValDt/{$prefix}DtTm", $entryNode);

        if (! $amount || ! $creditDebitIndicator) {
            Log::warning('CamtXmlHelper: skipping malformed entry — missing amount or credit/debit indicator', [
                'iban' => $this->iban ?? 'unknown',
                'has_amount' => (bool) $amount,
                'has_indicator' => (bool) $creditDebitIndicator,
            ]);

            return;
        }

        $date = $bookingDate ?? $valueDate ?? null;
        if ($date === null) {
            Log::warning('CamtXmlHelper: missing booking and value date — falling back to today', [
                'iban' => $this->iban ?? 'unknown',
            ]);
            $date = date('Y-m-d');
        }
        // Truncate datetime to date if needed
        if (strlen($date) > 10) {
            $date = substr($date, 0, 10);
        }

        $type = strtoupper($creditDebitIndicator) === 'CRDT' ? BankTransactionType::Credit : BankTransactionType::Debit;

        // Stage 3: Branch on transaction details — entries may contain multiple sub-transactions
        $transactionDetails = $xpath->query("{$prefix}NtryDtls/{$prefix}TxDtls", $entryNode);

        if ($transactionDetails->length > 0) {
            // Parse each transaction detail separately
            foreach ($transactionDetails as $detail) {
                $this->entries[] = $this->parseTxDetail($xpath, $detail, $prefix, $date, $amount, $currency, $type);
            }
        } else {
            // No transaction details — use entry-level info
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

    /**
     * Parse a single transaction detail (<TxDtls> element) into a CamtEntry.
     */
    private function parseTxDetail(\DOMXPath $xpath, \DOMElement $detail, string $prefix, string $date, string $fallbackAmount, ?string $fallbackCurrency, BankTransactionType $type): CamtEntry
    {
        // Stage 1: Amount — use detail-level if available, otherwise fall back to entry-level
        $txAmount = $this->contextText($xpath, "{$prefix}Amt", $detail) ?? $fallbackAmount;
        $txCurrency = $this->contextAttr($xpath, "{$prefix}Amt", $detail, 'Ccy') ?? $fallbackCurrency ?? 'CHF';

        // Stage 2: Reference identifiers (EndToEndId > AcctSvcrRef > PmtInfId)
        $endToEndId = $this->contextText($xpath, "{$prefix}Refs/{$prefix}EndToEndId", $detail);

        // Strip NOTPROVIDED end-to-end IDs
        if ($endToEndId && strtoupper($endToEndId) === 'NOTPROVIDED') {
            $endToEndId = null;
        }

        $ref = $endToEndId
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}AcctSvcrRef", $detail)
            ?? $this->contextText($xpath, "{$prefix}Refs/{$prefix}PmtInfId", $detail);

        // Stage 3: Related parties — debtor/creditor names from two possible XML paths
        $debtorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Nm", $detail)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Dbtr/{$prefix}Pty/{$prefix}Nm", $detail);

        $creditorName = $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Nm", $detail)
            ?? $this->contextText($xpath, "{$prefix}RltdPties/{$prefix}Cdtr/{$prefix}Pty/{$prefix}Nm", $detail);

        // Stage 4: Remittance info — description and structured reference (Swiss QR)
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
        $doc = new \DOMDocument;

        if (! $doc->loadXML($xml, LIBXML_NONET)) {
            $errors = libxml_get_errors();
            libxml_clear_errors();
            libxml_use_internal_errors($previousUseErrors);

            $msg = ! empty($errors) ? $errors[0]->message : 'Unknown XML error';
            throw new \InvalidArgumentException('Invalid XML: '.trim($msg));
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousUseErrors);

        return $doc;
    }

    private function xpathText(\DOMXPath $xpath, string $query): ?string
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
