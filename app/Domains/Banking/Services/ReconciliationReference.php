<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Models\BankTransaction;
use Illuminate\Support\Str;

trait ReconciliationReference
{
    private function buildReference(string $orgId, BankTransaction $transaction): string
    {
        $reference = self::REFERENCE_PREFIX.($transaction->reference ?? $transaction->id);

        if ($this->ledgerQuery->isDuplicateReference($orgId, $reference)) {
            $reference .= '-'.Str::uuid()->toString();
        }

        return $reference;
    }
}
