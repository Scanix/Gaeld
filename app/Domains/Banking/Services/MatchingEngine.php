<?php

namespace App\Domains\Banking\Services;

use App\Domains\Banking\Enums\BankMatchType;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Enums\MatchConfidence;
use App\Domains\Banking\Models\BankMatch;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\Money;
use Illuminate\Support\Collection;

/**
 * Finds candidate invoice matches for bank transactions and persists them.
 *
 * Priority order:
 *   1. QR reference match (confidence = 100)
 *   2. Amount + customer name match (confidence = 90)
 *   3. Heuristic match (confidence = 70)
 *
 * Results are stored in the bank_matches table.
 */
class MatchingEngine
{
    private const MAX_MATCH_CANDIDATES = 5;

    /**
     * Find and store matches for a bank transaction.
     *
     * Only credit transactions can match invoices.
     *
     * @return Collection<BankMatch>
     */
    public function findAndStoreMatches(BankTransaction $transaction): Collection
    {
        $orgId = $transaction->bankAccount->organization_id;
        $amount = Money::absoluteAmount((string) $transaction->amount);

        if ($transaction->type !== BankTransactionType::Credit) {
            return collect();
        }

        $matches = collect();

        // Priority 1: Exact QR reference match
        $qrMatch = $this->matchByQrReference($orgId, $transaction);
        if ($qrMatch) {
            $matches->push($qrMatch);

            return $this->storeMatches($transaction, $matches);
        }

        // Priority 2: Amount + customer name match
        $amountCustomerMatches = $this->matchByAmountAndCustomer($orgId, $transaction, $amount);
        $matches = $matches->merge($amountCustomerMatches);

        // Priority 3: Heuristic matching (amount or reference)
        $heuristicMatches = $this->matchByHeuristics($orgId, $transaction, $amount);
        $existingInvoiceIds = $matches->pluck('invoice_id')->toArray();
        $heuristicMatches = $heuristicMatches->filter(fn ($m) => ! in_array($m['invoice_id'], $existingInvoiceIds));
        $matches = $matches->merge($heuristicMatches);

        return $this->storeMatches($transaction, $matches);
    }

    /**
     * Match by exact QR reference (structured_reference ↔ invoice.qr_reference).
     *
     * @return array{invoice_id: string, confidence: int, match_type: BankMatchType}|null
     */
    private function matchByQrReference(string $orgId, BankTransaction $transaction): ?array
    {
        $ref = $transaction->structured_reference;
        if (! $ref) {
            return null;
        }

        $invoice = Invoice::where('organization_id', $orgId)
            ->where('qr_reference', $ref)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->first();

        if (! $invoice) {
            return null;
        }

        return [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::QrReference->value,
            'match_type' => BankMatchType::QrReference,
        ];
    }

    /**
     * Match by exact amount AND customer name.
     * Confidence: 90
     */
    private function matchByAmountAndCustomer(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        if (! $transaction->debtor_name) {
            return collect();
        }

        $invoices = Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereBetween('total', [
                bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
            ])
            ->with(['customer'])
            ->get();

        return $invoices->filter(function ($invoice) use ($transaction) {
            $contact = $invoice->customer;
            if (! $contact) {
                return false;
            }

            return str_contains(strtolower($transaction->debtor_name), strtolower($contact->name))
                || str_contains(strtolower($contact->name), strtolower($transaction->debtor_name));
        })->map(fn ($invoice) => [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::AmountAndCustomer->value,
            'match_type' => BankMatchType::AmountCustomer,
        ])->values();
    }

    /**
     * Match by amount OR reference (fallback).
     * Confidence: 70
     */
    private function matchByHeuristics(string $orgId, BankTransaction $transaction, string $amount): Collection
    {
        $query = Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->where(function ($q) use ($amount, $transaction) {
                $q->whereBetween('total', [
                    bcsub($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                    bcadd($amount, MatchConfidence::AMOUNT_TOLERANCE, 2),
                ]);

                if ($transaction->reference) {
                    $q->orWhere('number', 'like', '%' . $transaction->reference . '%');
                }
                if ($transaction->end_to_end_id) {
                    $q->orWhere('number', 'like', '%' . $transaction->end_to_end_id . '%');
                }
            })
            ->orderBy('total')
            ->limit(self::MAX_MATCH_CANDIDATES)
            ->get();

        return $query->map(fn ($invoice) => [
            'invoice_id' => $invoice->id,
            'confidence' => MatchConfidence::Heuristic->value,
            'match_type' => BankMatchType::Heuristic,
        ])->values();
    }

    /**
     * Persist match candidates to bank_matches table, replacing any unconfirmed existing matches.
     *
     * @return Collection<BankMatch>
     */
    private function storeMatches(BankTransaction $transaction, Collection $matches): Collection
    {
        BankMatch::where('bank_transaction_id', $transaction->id)
            ->where('is_confirmed', false)
            ->delete();

        return $matches->map(fn ($match) => BankMatch::create([
            'bank_transaction_id' => $transaction->id,
            'invoice_id' => $match['invoice_id'],
            'confidence' => $match['confidence'],
            'match_type' => $match['match_type'],
        ]));
    }
}
