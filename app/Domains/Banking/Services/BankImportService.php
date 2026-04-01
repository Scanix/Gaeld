<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Enums\CamtFormat;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\Camt\Camt053Parser;
use App\Domains\Banking\Services\Camt\Camt054Parser;
use App\Domains\Banking\Services\Camt\CamtEntry;
use Illuminate\Support\Facades\DB;

/**
 * Imports bank statement files (CAMT XML, CSV, MT940) and persists the resulting transactions.
 *
 * Handles CAMT.053/054, CSV with column mapping, and SWIFT MT940 formats,
 * with deduplication via import hashes to prevent double-imports.
 */
class BankImportService
{
    /** Delimiter used to separate fields in the deduplication hash input. */
    private const HASH_DELIMITER = '|';

    /**
     * Import a CAMT file and create bank transactions.
     *
     * Detects the CAMT format (053 or 054) automatically,
     * parses entries, deduplicates them, and persists
     * as BankTransaction records linked to a BankImport.
     *
     * @return BankImport The created import with its transactions
     *
     * @throws \InvalidArgumentException When the file format is unsupported
     */
    public function importCamtFile(
        BankAccount $bankAccount,
        string $xml,
        string $filename,
    ): BankImport {
        $format = $this->detectFormat($xml);
        $parsedStatement = $this->parseEntries($xml, $format);

        return DB::transaction(function () use ($bankAccount, $filename, $format, $parsedStatement) {
            $import = BankImport::create([
                'organization_id' => $bankAccount->organization_id,
                'bank_account_id' => $bankAccount->id,
                'filename' => $filename,
                'format' => $format->value,
                'statement_id' => $parsedStatement['statementId'],
                'transaction_count' => 0,
            ]);

            $existingHashes = BankTransaction::where('bank_account_id', $bankAccount->id)
                ->pluck('import_hash')
                ->flip();

            $count = 0;
            foreach ($parsedStatement['entries'] as $entry) {
                $hash = $this->computeHash($bankAccount->id, $entry);

                // Skip duplicates
                if ($existingHashes->has($hash)) {
                    continue;
                }

                BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'bank_import_id' => $import->id,
                    'date' => $entry->date,
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'type' => $entry->type->value,
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
     * Import a CSV bank statement file with column mapping.
     *
     * @param  array{date: int, amount: int, description: ?int, reference: ?int}  $mapping
     */
    public function importCsvFile(
        BankAccount $bankAccount,
        string $content,
        string $filename,
        array $mapping,
        string $delimiter = ',',
    ): BankImport {
        $parser = new CsvBankParser;
        $parser->parse($content, $mapping, $delimiter);

        return $this->persistEntries(
            $bankAccount,
            $filename,
            CamtFormat::Csv,
            null,
            $parser->getEntries(),
        );
    }

    /**
     * Import an MT940 bank statement file.
     */
    public function importMt940File(
        BankAccount $bankAccount,
        string $content,
        string $filename,
    ): BankImport {
        $parser = new Mt940Parser;
        $parser->parse($content);

        return $this->persistEntries(
            $bankAccount,
            $filename,
            CamtFormat::Mt940,
            $parser->getStatementId(),
            $parser->getEntries(),
        );
    }

    /**
     * Persist parsed entries as bank transactions.
     *
     * @param  CamtEntry[]  $entries
     */
    private function persistEntries(
        BankAccount $bankAccount,
        string $filename,
        CamtFormat $format,
        ?string $statementId,
        array $entries,
    ): BankImport {
        return DB::transaction(function () use ($bankAccount, $filename, $format, $statementId, $entries) {
            $import = BankImport::create([
                'organization_id' => $bankAccount->organization_id,
                'bank_account_id' => $bankAccount->id,
                'filename' => $filename,
                'format' => $format->value,
                'statement_id' => $statementId,
                'transaction_count' => 0,
            ]);

            $existingHashes = BankTransaction::where('bank_account_id', $bankAccount->id)
                ->pluck('import_hash')
                ->flip();

            $count = 0;
            foreach ($entries as $entry) {
                $hash = $this->computeHash($bankAccount->id, $entry);

                if ($existingHashes->has($hash)) {
                    continue;
                }

                BankTransaction::create([
                    'bank_account_id' => $bankAccount->id,
                    'bank_import_id' => $import->id,
                    'date' => $entry->date,
                    'description' => $entry->description,
                    'amount' => $entry->amount,
                    'type' => $entry->type->value,
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
    private function detectFormat(string $xml): CamtFormat
    {
        if (str_contains($xml, 'BkToCstmrStmt')) {
            return CamtFormat::Camt053;
        }

        if (str_contains($xml, 'BkToCstmrDbtCdtNtfctn')) {
            return CamtFormat::Camt054;
        }

        throw new \InvalidArgumentException('Unsupported CAMT format. Only CAMT.053 and CAMT.054 are supported.');
    }

    /**
     * @return array{entries: CamtEntry[], statementId: ?string}
     */
    private function parseEntries(string $xml, CamtFormat $format): array
    {
        if ($format === CamtFormat::Camt053) {
            $parser = new Camt053Parser;
            $parser->parse($xml);

            return ['entries' => $parser->getEntries(), 'statementId' => $parser->getStatementId()];
        }

        $parser = new Camt054Parser;
        $parser->parse($xml);

        return ['entries' => $parser->getEntries(), 'statementId' => $parser->getNotificationId()];
    }

    /**
     * Compute a deduplication hash for a CAMT entry.
     */
    private function computeHash(int $bankAccountId, CamtEntry $entry): string
    {
        return hash('sha256', implode(self::HASH_DELIMITER, [
            $bankAccountId,
            $entry->date,
            $entry->amount,
            $entry->type->value,
            $entry->reference ?? '',
            $entry->endToEndId ?? '',
            $entry->description ?? '',
        ]));
    }
}
