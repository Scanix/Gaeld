<?php

namespace App\Domains\Invoicing\DTOs;

use App\Domains\Invoicing\Enums\PaymentMethod;
use App\Support\MapsToSnakeCase;
use App\Support\ValidatesFromArray;

/**
 * DTO for recording a payment against an invoice.
 */
readonly class RecordPaymentData
{
    use MapsToSnakeCase;
    use ValidatesFromArray;

    public function __construct(
        public string $amount,
        public string $paymentDate,
        public PaymentMethod $paymentMethod,
        public ?string $reference,
        public ?string $bankAccountCode = null,
    ) {}

    public static function fromArray(array $data): self
    {
        self::assertRequired($data, ['amount', 'payment_date', 'payment_method']);

        return new self(
            amount: (string) $data['amount'],
            paymentDate: $data['payment_date'],
            paymentMethod: PaymentMethod::from($data['payment_method']),
            reference: $data['reference'] ?? null,
            bankAccountCode: $data['bank_account_code'] ?? null,
        );
    }

}
