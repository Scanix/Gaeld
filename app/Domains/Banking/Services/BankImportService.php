<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\Camt\Camt053Parser;
use App\Domains\Banking\Services\Camt\Camt054Parser;
use App\Domains\Banking\Services\Camt\CamtEntry;
use Illuminate\Support\Facades\DB;

class BankImportService
{
    /**
     * Import a CAMT file and create bank transactions.
     *
     * Detects the CAMT format (053 or 054) automatically,
     * parses entries, deduplicates them, and persists
     * as BankTransaction records linked to a BankImport.
     *
     * @return BankImport  The created import with its transactions
     *
     * @throws \InvalidArgumentException  When the file format is unsupported
     */
    public function importCamtFile(
        BankAccount $bankAccount,
        string $xml,
        string $filename,
    ): BankImport {
        $format = $this->detectFormat($xml);
        $entries = $this->parseEntries($xml, $format);

        return DB::transaction(function () use ($bankAccount, $filename, $format, $entries) {
            $import = BankImport::create([
                'organization_id' => $bankAccount->organization_id,
                'bank_account_id' => $bankAccount->id,
                'filename' => $filename,
                'format' => $format,
                'statement_id' => $this->getStatementId($format),
                'transaction_count' => 0,
            ]);

            $count = 0;
            foreach ($entries as $entry) {
                $hash = $this->computeHash($bankAccount->id, $entry);

                // Skip duplicates
                if (BankTransaction::where('import_hash', $hash)->exists()) {
                    continue;
                }

                BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'bank_import_id' => $import->id,
                    'date' => $entry->date,
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'type' => $entry->type,
                    'reference' => $entry->reference,
                    'debtor_name' => $entry->debtorName,
                    'creditor_name' => $entry->creditorName,
                    'end_to_end_id' => $entry->endToEndId,
                    'structured_reference' => $entry->structuredReference,
                    'import_hash' => $hash,
                    'is_reconciled' => false,
                ]);

                $count++;
            }

            $import->update(['transaction_count' => $count]);

            return $import->load('transactions');
        });
    }

    /**
     * Detect CAMT format from XML content.
     */
    private function detectFormat(string $xml): string
    {
        if (str_contains($xml, 'BkToCstmrStmt')) {
            return BankImport::FORMAT_CAMT053;
        }

        if (str_contains($xml, 'BkToCstmrDbtCdtNtfctn')) {
            return BankImport::FORMAT_CAMT054;
        }

        throw new \InvalidArgumentException('Unsupported CAMT format. Only CAMT.053 and CAMT.054 are supported.');
    }

    private Camt053Parser|Camt054Parser|null $activeParser = null;

    /**
     * @return CamtEntry[]
     */
    private function parseEntries(string $xml, string $format): array
    {
        if ($format === BankImport::FORMAT_CAMT053) {
            $parser = new Camt053Parser();
            $parser->parse($xml);
            $this->activeParser = $parser;

            return $parser->getEntries();
        }

        $parser = new Camt054Parser();
        $parser->parse($xml);
        $this->activeParser = $parser;

        return $parser->getEntries();
    }

    private function getStatementId(string $format): ?string
    {
        if (! $this->activeParser) {
            return null;
        }

        if ($format === BankImport::FORMAT_CAMT053 && $this->activeParser instanceof Camt053Parser) {
            return $this->activeParser->getStatementId();
        }

        if ($this->activeParser instanceof Camt054Parser) {
            return $this->activeParser->getNotificationId();
        }

        return null;
    }

    /**
     * Compute a deduplication hash for a CAMT entry.
     */
    private function computeHash(int $bankAccountId, CamtEntry $entry): string
    {
        return hash('sha256', implode('|', [
            $bankAccountId,
            $entry->date,
            $entry->amount,
            $entry->type,
            $entry->reference ?? '',
            $entry->endToEndId ?? '',
            $entry->description ?? '',
        ]));
    }
}
