<?php

namespace App\Domains\Invoicing\DTOs;

use Illuminate\Http\Request;

readonly class UpdateInvoiceData
{
    /**
     * @param array<int, array{description: string, quantity: string, unit_price: string, vat_rate_id: ?string, sort_order?: int}> $lines
     */
    public function __construct(
        public string $clientId,
        public string $number,
        public string $issueDate,
        public string $dueDate,
        public string $currency,
        public ?string $notes,
        public ?string $paymentTerms,
        public array $lines,
    ) {}

    public static function fromRequest(Request $request): self
    {
        $validated = $request->validate([
            'client_id' => 'required|exists:clients,id',
            'number' => 'required|string|max:50',
            'issue_date' => 'required|date',
            'due_date' => 'required|date|after_or_equal:issue_date',
            'currency' => 'string|size:3',
            'notes' => 'nullable|string',
            'payment_terms' => 'nullable|string',
            'lines' => 'required|array|min:1',
            'lines.*.description' => 'required|string',
            'lines.*.quantity' => 'required|numeric|min:0.01',
            'lines.*.unit_price' => 'required|numeric|min:0',
            'lines.*.vat_rate_id' => 'nullable|exists:vat_rates,id',
        ]);

        return new self(
            clientId: $validated['client_id'],
            number: $validated['number'],
            issueDate: $validated['issue_date'],
            dueDate: $validated['due_date'],
            currency: $validated['currency'] ?? 'CHF',
            notes: $validated['notes'] ?? null,
            paymentTerms: $validated['payment_terms'] ?? null,
            lines: $validated['lines'],
        );
    }

    public function toArray(): array
    {
        return [
            'client_id' => $this->clientId,
            'number' => $this->number,
            'issue_date' => $this->issueDate,
            'due_date' => $this->dueDate,
            'currency' => $this->currency,
            'notes' => $this->notes,
            'payment_terms' => $this->paymentTerms,
        ];
    }
}
