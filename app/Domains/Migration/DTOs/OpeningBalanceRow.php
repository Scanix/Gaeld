<?php

namespace App\Domains\Migration\DTOs;

class OpeningBalanceRow extends AbstractImportRow
{
    public function __construct(
        int $sourceRow,
        public readonly string $accountCode,
        public readonly ?string $accountName = null,
        public readonly ?string $debit = null,
        public readonly ?string $credit = null,
    ) {
        parent::__construct($sourceRow);
    }

    public function toArray(): array
    {
        return [
            'account_code' => $this->accountCode,
            'account_name' => $this->accountName,
            'debit' => $this->debit,
            'credit' => $this->credit,
        ];
    }
}
