<?php

namespace App\Domains\Banking\Services\Camt;

trait CamtXmlHelper
{
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
