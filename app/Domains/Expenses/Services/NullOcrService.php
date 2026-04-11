<?php

namespace App\Domains\Expenses\Services;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\DTOs\ReceiptOcrResult;

/**
 * No-op OCR driver — used when OCR_DRIVER is not 'tesseract' or Tesseract
 * is not available. Returns an empty result so the caller can still let the
 * user fill in fields manually.
 */
class NullOcrService implements ReceiptOcrInterface
{
    public function extract(string $imagePath): ReceiptOcrResult
    {
        return new ReceiptOcrResult(rawText: '');
    }
}
