<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Services\Camt\CamtEntry;

/**
 * Parses SWIFT MT940 bank statement files into CamtEntry DTOs.
 *
 * Supports the standard MT940 format used by most Swiss and European banks.
 */
class Mt940Parser
{
    /** @var CamtEntry[] */
    private array $entries = [];

    private ?string $statementId = null;

    public function parse(string $content): void
    {
        $this->entries = [];
        $this->statementId = null;

        // Extract statement number from :20: tag
        if (preg_match('/:20:(.+)/m', $content, $m)) {
            $this->statementId = trim($m[1]);
        }

        // Split into transaction blocks using :61: tag
        $blocks = preg_split('/:61:/', $content);
        array_shift($blocks); // Remove header

        foreach ($blocks as $block) {
            $entry = $this->parseTransaction($block);
            if ($entry) {
                $this->entries[] = $entry;
            }
        }
    }

    /** @return CamtEntry[] */
    public function getEntries(): array
    {
        return $this->entries;
    }

    public function getStatementId(): ?string
    {
        return $this->statementId;
    }

    private function parseTransaction(string $block): ?CamtEntry
    {
        $lines = explode("\n", $block);
        $firstLine = trim($lines[0] ?? '');

        if (strlen($firstLine) < 16) {
            return null;
        }

        // :61: format: YYMMDD[MMDD]C/DamountS...reference
        // Date: first 6 chars (YYMMDD)
        $dateStr = substr($firstLine, 0, 6);
        $year = (int) substr($dateStr, 0, 2);
        $month = (int) substr($dateStr, 2, 2);
        $day = (int) substr($dateStr, 4, 2);
        $fullYear = ($year > 70) ? 1900 + $year : 2000 + $year;
        $date = sprintf('%04d-%02d-%02d', $fullYear, $month, $day);

        // Find credit/debit indicator and amount
        // After optional entry date (4 chars), next char is C/D/RC/RD
        $rest = substr($firstLine, 6);

        // Skip optional entry date (4 digits)
        if (preg_match('/^\d{4}/', $rest)) {
            $rest = substr($rest, 4);
        }

        // Credit/Debit indicator
        $type = BankTransactionType::Credit;
        if (str_starts_with($rest, 'RD') || str_starts_with($rest, 'D')) {
            $type = BankTransactionType::Debit;
        }

        // Skip C/D/RC/RD prefix
        $rest = preg_replace('/^R?[CD]/', '', $rest);

        // Optional currency letter
        $rest = preg_replace('/^[A-Z]/', '', $rest);

        // Extract amount (digits with optional comma/period)
        if (preg_match('/^([\d,.]+)/', $rest, $m)) {
            $amount = str_replace(',', '.', $m[1]);
        } else {
            return null;
        }

        // Extract description from :86: tag
        $description = null;
        $reference = null;
        $fullBlock = implode("\n", $lines);

        if (preg_match('/:86:(.+?)(?=\n:|$)/s', $fullBlock, $m)) {
            $description = trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        // Extract reference from the :61: line (after amount+type code)
        $restAfterAmount = preg_replace('/^[\d,.]+[A-Z]{4}/', '', $rest);
        if ($restAfterAmount && trim($restAfterAmount) !== '') {
            $reference = trim($restAfterAmount);
        }

        return new CamtEntry(
            date: $date,
            amount: $amount,
            currency: 'CHF',
            type: $type,
            reference: $reference,
            description: $description,
            iban: null,
            debtorName: null,
            creditorName: null,
            endToEndId: null,
        );
    }
}
