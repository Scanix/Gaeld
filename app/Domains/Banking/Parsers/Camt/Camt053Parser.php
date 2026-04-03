<?php

namespace App\Domains\Banking\Parsers\Camt;

/**
 * Parser for CAMT.053 (Bank-to-Customer Statement) XML files.
 *
 * Extracts individual transaction entries from bank statements and
 * normalizes them into CamtEntry DTOs for import into the system.
 *
 * Supports ISO 20022 camt.053.001.02 through .08.
 */
class Camt053Parser
{
    use CamtXmlHelper;

    /** @var CamtEntry[] */
    private array $entries = [];

    private ?string $iban = null;

    private ?string $statementId = null;

    private ?string $creationDate = null;

    /**
     * Parse a CAMT.053 XML string.
     *
     * @param  string  $xml  Raw XML content of the CAMT.053 file
     *
     * @throws \InvalidArgumentException When the XML is malformed or not a valid CAMT.053
     */
    public function parse(string $xml): self
    {
        $this->entries = [];

        $doc = $this->loadXml($xml);
        $xpath = new \DOMXPath($doc);

        // Register the namespace — works across CAMT.053 versions
        $ns = $doc->documentElement->lookupNamespaceURI(null) ?? '';
        if ($ns) {
            $xpath->registerNamespace('c', $ns);
            $prefix = 'c:';
        } else {
            $prefix = '';
        }

        // Validate root element
        $root = $xpath->query("//{$prefix}BkToCstmrStmt");
        if ($root->length === 0) {
            throw new \InvalidArgumentException('Not a valid CAMT.053 file: BkToCstmrStmt element not found.');
        }

        // Extract statement-level metadata
        $this->statementId = $this->xpathText($xpath, "//{$prefix}BkToCstmrStmt/{$prefix}Stmt/{$prefix}Id");
        $this->creationDate = $this->xpathText($xpath, "//{$prefix}BkToCstmrStmt/{$prefix}Stmt/{$prefix}CreDtTm");
        $this->iban = $this->xpathText($xpath, "//{$prefix}BkToCstmrStmt/{$prefix}Stmt/{$prefix}Acct/{$prefix}Id/{$prefix}IBAN");

        // Parse entries — each <Ntry> is a bank statement entry
        $entries = $xpath->query("//{$prefix}BkToCstmrStmt/{$prefix}Stmt/{$prefix}Ntry");

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

    public function getStatementId(): ?string
    {
        return $this->statementId;
    }

    public function getCreationDate(): ?string
    {
        return $this->creationDate;
    }
}
