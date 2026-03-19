<?php

namespace Tests\Unit\Rules;

use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Rules\QrReferencePaymentRule;
use App\Domains\Banking\Rules\RecurringEntryRule;
use App\Domains\Banking\Rules\SupplierCategoryRule;
use App\Domains\Banking\Services\ReconciliationService;
use Tests\TestCase;

class RuleConfidenceTest extends TestCase
{
    public function test_qr_reference_rule_has_confidence_100(): void
    {
        $reconciliation = $this->createMock(ReconciliationService::class);
        $rule = new QrReferencePaymentRule($reconciliation);

        $this->assertEquals(100, $rule->confidence());
        $this->assertEquals('QR Reference Payment', $rule->name());
    }

    public function test_supplier_category_rule_has_confidence_90(): void
    {
        $rule = new SupplierCategoryRule();

        $this->assertEquals(90, $rule->confidence());
        $this->assertEquals('Supplier Category Suggestion', $rule->name());
    }

    public function test_recurring_entry_rule_has_confidence_70(): void
    {
        $rule = new RecurringEntryRule();

        $this->assertEquals(70, $rule->confidence());
        $this->assertEquals('Recurring Entry Detection', $rule->name());
    }

    public function test_qr_rule_rejects_debit_transaction(): void
    {
        $reconciliation = $this->createMock(ReconciliationService::class);
        $rule = new QrReferencePaymentRule($reconciliation);

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Debit;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_qr_rule_rejects_credit_without_structured_reference(): void
    {
        $reconciliation = $this->createMock(ReconciliationService::class);
        $rule = new QrReferencePaymentRule($reconciliation);

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Credit;
        $transaction->structured_reference = null;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_supplier_rule_rejects_credit_transaction(): void
    {
        $rule = new SupplierCategoryRule();

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Credit;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_supplier_rule_rejects_debit_without_creditor(): void
    {
        $rule = new SupplierCategoryRule();

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Debit;
        $transaction->creditor_name = null;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_recurring_rule_rejects_credit_transaction(): void
    {
        $rule = new RecurringEntryRule();

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Credit;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_recurring_rule_rejects_debit_without_description(): void
    {
        $rule = new RecurringEntryRule();

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Debit;
        $transaction->description = null;
        $transaction->amount = '100.00';

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_recurring_rule_rejects_debit_without_amount(): void
    {
        $rule = new RecurringEntryRule();

        $transaction = new BankTransaction();
        $transaction->type = BankTransactionType::Debit;
        $transaction->description = 'GitHub subscription';
        $transaction->amount = null;

        $this->assertFalse($rule->matches($transaction));
    }

    public function test_qr_rule_skips_apply_for_reconciled_transaction(): void
    {
        $reconciliation = $this->createMock(ReconciliationService::class);
        $reconciliation->expects($this->never())->method('findAndStoreMatches');

        $rule = new QrReferencePaymentRule($reconciliation);

        $transaction = new BankTransaction();
        $transaction->forceFill(['is_reconciled' => true]);

        $rule->apply($transaction);
    }
}
