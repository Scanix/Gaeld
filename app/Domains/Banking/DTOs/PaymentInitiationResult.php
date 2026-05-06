<?php

namespace App\Domains\Banking\DTOs;

use Symfony\Component\HttpFoundation\Response;

/**
 * Result of a payment-initiation request.
 *
 * Two variants:
 *   - File download (V1, FilePain001Provider): returns the pain.001 XML response.
 *   - Remote submission (Phase 2, BlinkPaymentProvider): returns a remote batch id.
 *
 * Always carries the count and total of instructions for UI feedback.
 */
readonly class PaymentInitiationResult
{
    public function __construct(
        public int $count,
        public string $totalAmount,
        public string $currency,
        public ?Response $download = null,
        public ?string $remoteBatchId = null,
        public ?string $message = null,
    ) {}

    public static function file(Response $download, int $count, string $totalAmount, string $currency): self
    {
        return new self(
            count: $count,
            totalAmount: $totalAmount,
            currency: $currency,
            download: $download,
        );
    }

    public static function remote(string $remoteBatchId, int $count, string $totalAmount, string $currency, ?string $message = null): self
    {
        return new self(
            count: $count,
            totalAmount: $totalAmount,
            currency: $currency,
            remoteBatchId: $remoteBatchId,
            message: $message,
        );
    }

    public function isFile(): bool
    {
        return $this->download !== null;
    }
}
