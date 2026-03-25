<?php

namespace App\Domains\Expenses\DTOs;

readonly class ReceiptOcrResult
{
    public function __construct(
        public string $rawText,
        public ?float $amount = null,
        public ?string $date = null,
        public ?string $vendor = null,
        public ?float $confidence = null,
    ) {}

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'date' => $this->date,
            'vendor' => $this->vendor,
            'raw_text' => $this->rawText,
            'confidence' => $this->confidence,
        ];
    }
}
