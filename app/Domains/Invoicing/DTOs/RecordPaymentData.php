<?php

namespace App\Domains\Invoicing\DTOs;

use Illuminate\Http\Request;

readonly class RecordPaymentData
{
    public function __construct(
        public string $amount,
        public string $paymentDate,
        public string $paymentMethod,
        public ?string $reference,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'payment_date' => 'required|date',
            'payment_method' => 'required|in:bank,cash,card',
            'reference' => 'nullable|string|max:100',
        ]);

        return new self(
            amount: $validated['amount'],
            paymentDate: $validated['payment_date'],
            paymentMethod: $validated['payment_method'],
            reference: $validated['reference'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'amount' => $this->amount,
            'payment_date' => $this->paymentDate,
            'payment_method' => $this->paymentMethod,
            'reference' => $this->reference,
        ];
    }
}
