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
