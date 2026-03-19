<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;

/**
 * EE Rule: Auto-reconcile when bank transaction has an exact QR reference match.
 *
 * Confidence: 100 (exact reference match — always auto-confirms).
 */
class QrReferencePaymentRule extends BaseRule
{
    public function __construct(
        private ReconciliationService $reconciliationService,
    ) {}

    public function name(): string
    {
        return 'QR Reference Payment';
    }

    public function confidence(): int
    {
        return 100;
    }

    public function matches(BankTransaction $transaction): bool
    {
        if ($transaction->type !== BankTransactionType::Credit) {
            return false;
        }

        if (! $transaction->structured_reference) {
            return false;
        }

        $orgId = $transaction->bankAccount->organization_id;

        return Invoice::where('organization_id', $orgId)
            ->where('qr_reference', $transaction->structured_reference)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->exists();
    }

    public function apply(BankTransaction $transaction): void
    {
        if ($transaction->is_reconciled) {
            return;
        }

        $matches = $this->reconciliationService->findMatches($transaction);
        $exactMatch = $matches->first(fn ($m) => $m->confidence === 100);

        if ($exactMatch) {
            $this->reconciliationService->confirmMatch($exactMatch);
        }
    }
}
