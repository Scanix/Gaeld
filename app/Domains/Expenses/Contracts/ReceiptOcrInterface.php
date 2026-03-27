<?php

namespace App\Domains\Expenses\Contracts;

use App\Domains\Expenses\DTOs\ReceiptOcrResult;

interface ReceiptOcrInterface
{
    /**
     * Extract text and structured fields from a receipt image.
     *
     * @param  string  $imagePath  Absolute path to the image file on disk.
     */
    public function extract(string $imagePath): ReceiptOcrResult;
}
