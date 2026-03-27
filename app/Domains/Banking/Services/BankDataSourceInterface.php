<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\Camt\CamtEntry;
use Illuminate\Support\Carbon;

interface BankDataSourceInterface
{
    /**
     * Fetch bank transactions for the given account and date range.
     *
     * Implementations must normalise results to CamtEntry value objects
     * so that the BankSyncService can persist them uniformly regardless
     * of the underlying data source (CAMT file, Blink API, etc.).
     *
     * @return CamtEntry[]
     */
    public function fetchTransactions(BankAccount $account, Carbon $from, Carbon $to): array;
}
