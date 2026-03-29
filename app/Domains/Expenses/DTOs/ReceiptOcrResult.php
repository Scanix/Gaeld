<?php

namespace App\Domains\Expenses\DTOs;

use App\Support\MapsToSnakeCase;

/**
 * Structured result returned by the receipt OCR pipeline.
 *
 * Contains the raw extracted text and optional parsed fields
 * (amount, date, vendor) with a confidence score.
 */
readonly class ReceiptOcrResult
{
    use MapsToSnakeCase;
    public function __construct(
        public string $rawText,
        public ?float $amount = null,
        public ?string $date = null,
        public ?string $vendor = null,
        public ?float $confidence = null,
    ) {}

}
