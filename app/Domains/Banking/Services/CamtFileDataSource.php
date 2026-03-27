<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\Camt\Camt053Parser;
use App\Domains\Banking\Services\Camt\Camt054Parser;
use App\Domains\Banking\Services\Camt\CamtEntry;
use Illuminate\Support\Carbon;

/**
 * CAMT file adapter — implements the BankDataSourceInterface for
 * file-based imports (CAMT.053 / CAMT.054).
 *
 * This adapter is used by the BankSyncService when sync_provider = 'camt'.
 * XML content must be supplied externally; the interface date range is used
 * only to filter entries returned by the parser.
 */
class CamtFileDataSource implements BankDataSourceInterface
{
    private string $xml = '';

    public function withXml(string $xml): static
    {
        $clone = clone $this;
        $clone->xml = $xml;

        return $clone;
    }

    /**
     * {@inheritdoc}
     *
     * @return CamtEntry[]
     */
    public function fetchTransactions(BankAccount $account, Carbon $from, Carbon $to): array
    {
        if ($this->xml === '') {
            return [];
        }

        $entries = $this->parse($this->xml);

        // Filter by date range
        return array_filter($entries, function (CamtEntry $e) use ($from, $to) {
            $date = Carbon::parse($e->date);

            return $date->between($from, $to);
        });
    }

    /** @return CamtEntry[] */
    private function parse(string $xml): array
    {
        if (str_contains($xml, 'BkToCstmrStmt')) {
            $parser = new Camt053Parser;
            $parser->parse($xml);

            return $parser->getEntries();
        }

        if (str_contains($xml, 'BkToCstmrDbtCdtNtfctn')) {
            $parser = new Camt054Parser;
            $parser->parse($xml);

            return $parser->getEntries();
        }

        throw new \InvalidArgumentException('Unsupported CAMT format.');
    }
}
