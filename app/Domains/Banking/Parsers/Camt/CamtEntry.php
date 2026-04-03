<?php

namespace App\Domains\Banking\Parsers\Camt;

use App\Domains\Banking\Enums\BankTransactionType;

/**
 * Normalized bank transaction extracted from a CAMT file.
 *
 * Both CAMT.053 (statements) and CAMT.054 (notifications) produce
 * these DTOs, which are then persisted as BankTransaction records.
 */
class CamtEntry
{
    public function __construct(
        public readonly string $date,
        public readonly string $amount,
        public readonly string $currency,
        public readonly BankTransactionType $type,
        public readonly ?string $reference,
        public readonly ?string $description,
        public readonly ?string $iban,
        public readonly ?string $debtorName,
        public readonly ?string $creditorName,
        public readonly ?string $endToEndId,
        public readonly ?string $structuredReference = null,
    ) {}
}
