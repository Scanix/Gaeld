<?php

namespace App\Domains\Banking\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\MatchingService;
use App\Domains\Banking\Services\ReconciliationService;
use App\Domains\Invoicing\Queries\InvoiceReportingQuery;

/**
 * EE Rule: Auto-reconcile when bank transaction has an exact QR reference match.
 *
 * Confidence: 100 (exact reference match — always auto-confirms).
 */
class QrReferencePaymentRule extends BaseRule
{
    public function __construct(
        private MatchingService $matchingService,
        private ReconciliationService $reconciliationService,
        private InvoiceReportingQuery $invoiceQuery,
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

        return $this->invoiceQuery->hasMatchingQrReference($orgId, $transaction->structured_reference);
    }

    public function apply(BankTransaction $transaction): void
    {
        if ($transaction->is_reconciled) {
            return;
        }

        $matches = $this->matchingService->findAndStoreMatches($transaction);
        $exactMatch = $matches->first(fn ($m) => $m->confidence === 100);

        if ($exactMatch) {
            $this->reconciliationService->confirmMatch($exactMatch);
        }
    }
}
