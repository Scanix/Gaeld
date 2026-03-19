<?php

namespace App\Domains\Banking\Services;

use App\Exceptions\FeatureDisabledException;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Rules\BaseRule;
use App\Domains\Banking\Rules\QrReferencePaymentRule;
use App\Domains\Banking\Rules\RecurringEntryRule;
use App\Domains\Banking\Rules\SupplierCategoryRule;
use App\Services\FeatureFlag;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

/**
 * EE Service: Runs all configured automation rules against a bank transaction.
 *
 * Rules are evaluated in descending confidence order. The highest-confidence
 * matching rule is applied automatically; lower-confidence matches are stored
 * as suggestions for the user to confirm.
 *
 * Auto-apply threshold: 100 (only exact matches trigger automated writes).
 *
 * Guarded by the 'rule_engine' feature flag — throws in CE.
 */
class RuleEngineService
{
    /** Confidence threshold above which a rule is auto-applied without confirmation. */
    private const AUTO_APPLY_THRESHOLD = 100;

    /** @var BaseRule[] */
    private array $rules;

    public function __construct(
        QrReferencePaymentRule $qrRule,
        SupplierCategoryRule $supplierRule,
        RecurringEntryRule $recurringRule,
    ) {
        $this->rules = [$qrRule, $supplierRule, $recurringRule];
    }

    /**
     * Run all rules against a transaction.
     *
     * Returns a Collection of matching rules sorted by confidence (desc).
     * Rules at or above AUTO_APPLY_THRESHOLD are applied immediately.
     *
     * @return Collection<array{rule: BaseRule, confidence: int, applied: bool}>
     *
     * @throws FeatureDisabledException in CE (feature flag disabled)
     */
    public function run(BankTransaction $transaction): Collection
    {
        if (FeatureFlag::disabled('rule_engine')) {
            throw new FeatureDisabledException('rule_engine');
        }

        if ($transaction->is_reconciled) {
            return collect();
        }

        $results = collect();

        foreach ($this->rules as $rule) {
            try {
                if (! $rule->matches($transaction)) {
                    continue;
                }

                $applied = false;

                if ($rule->confidence() >= self::AUTO_APPLY_THRESHOLD) {
                    $rule->apply($transaction);
                    $applied = true;
                }

                $results->push([
                    'rule' => $rule,
                    'confidence' => $rule->confidence(),
                    'applied' => $applied,
                ]);
            } catch (\Exception $e) {
                Log::warning('RuleEngineService: rule failed', [
                    'rule' => $rule->name(),
                    'transaction_id' => $transaction->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $results->sortByDesc('confidence')->values();
    }

    /**
     * Run the rule engine on all unreconciled transactions for an organization.
     *
     * @return array{processed: int, matched: int, applied: int}
     *
     * @throws FeatureDisabledException in CE
     */
    public function runForOrganization(string $organizationId): array
    {
        if (FeatureFlag::disabled('rule_engine')) {
            throw new FeatureDisabledException('rule_engine');
        }

        $transactions = BankTransaction::whereHas(
            'bankAccount',
            fn ($q) => $q->where('organization_id', $organizationId)
        )
            ->where('is_reconciled', false)
            ->get();

        $processed = 0;
        $matched = 0;
        $applied = 0;

        foreach ($transactions as $transaction) {
            $results = $this->run($transaction);
            $processed++;

            if ($results->isNotEmpty()) {
                $matched++;
                $applied += $results->where('applied', true)->count();
            }
        }

        return compact('processed', 'matched', 'applied');
    }
}
