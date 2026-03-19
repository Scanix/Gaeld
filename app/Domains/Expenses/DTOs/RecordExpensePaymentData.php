<?php

namespace App\Domains\Expenses\DTOs;

readonly class RecordExpensePaymentData
{
    public function __construct(
        public string $amount,
        public string $paymentDate,
        public string $reference,
        public string $description,
        public string $expenseAccountCode,
        public ?string $bankAccountCode = null,
    ) {}
}
