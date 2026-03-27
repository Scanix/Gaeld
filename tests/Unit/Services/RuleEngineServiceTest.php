<?php

namespace Tests\Unit\Services;

use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Rules\QrReferencePaymentRule;
use App\Domains\Banking\Rules\RecurringEntryRule;
use App\Domains\Banking\Rules\SupplierCategoryRule;
use App\Domains\Banking\Services\RuleEngineService;
use App\Support\Exceptions\FeatureDisabledException;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RuleEngineServiceTest extends TestCase
{
    private QrReferencePaymentRule $qrRule;

    private SupplierCategoryRule $supplierRule;

    private RecurringEntryRule $recurringRule;

    private RuleEngineService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->qrRule = $this->createMock(QrReferencePaymentRule::class);
        $this->supplierRule = $this->createMock(SupplierCategoryRule::class);
        $this->recurringRule = $this->createMock(RecurringEntryRule::class);

        $this->service = new RuleEngineService(
            $this->qrRule,
            $this->supplierRule,
            $this->recurringRule,
        );
    }

    public function test_run_throws_when_feature_disabled(): void
    {
        Config::set('features.rule_engine', false);

        $transaction = $this->createMock(BankTransaction::class);

        $this->expectException(FeatureDisabledException::class);
        $this->expectExceptionMessage('rule_engine');

        $this->service->evaluateRules($transaction);
    }

    public function test_run_returns_empty_for_reconciled_transaction(): void
    {
        Config::set('features.rule_engine', true);

        $transaction = $this->createMock(BankTransaction::class);
        $transaction->is_reconciled = true;

        $results = $this->service->evaluateRules($transaction);

        $this->assertTrue($results->isEmpty());
    }

    public function test_run_collects_matching_rules(): void
    {
        Config::set('features.rule_engine', true);

        $transaction = $this->createMock(BankTransaction::class);
        $transaction->is_reconciled = false;

        $this->qrRule->method('matches')->willReturn(false);
        $this->qrRule->method('confidence')->willReturn(100);
        $this->qrRule->method('name')->willReturn('QR');

        $this->supplierRule->method('matches')->willReturn(true);
        $this->supplierRule->method('confidence')->willReturn(90);
        $this->supplierRule->method('name')->willReturn('Supplier');

        $this->recurringRule->method('matches')->willReturn(true);
        $this->recurringRule->method('confidence')->willReturn(70);
        $this->recurringRule->method('name')->willReturn('Recurring');

        $results = $this->service->evaluateRules($transaction);

        $this->assertCount(2, $results);
        // Results are sorted desc by confidence
        $this->assertEquals(90, $results->first()['confidence']);
        $this->assertEquals(70, $results->last()['confidence']);
    }

    public function test_run_auto_applies_rules_at_threshold(): void
    {
        Config::set('features.rule_engine', true);

        $transaction = $this->createMock(BankTransaction::class);
        $transaction->is_reconciled = false;

        $this->qrRule->method('matches')->willReturn(true);
        $this->qrRule->method('confidence')->willReturn(100);
        $this->qrRule->method('name')->willReturn('QR');
        $this->qrRule->expects($this->once())->method('apply')->with($transaction);

        $this->supplierRule->method('matches')->willReturn(true);
        $this->supplierRule->method('confidence')->willReturn(90);
        $this->supplierRule->method('name')->willReturn('Supplier');
        // Supplier at 90 should NOT be auto-applied
        $this->supplierRule->expects($this->never())->method('apply');

        $this->recurringRule->method('matches')->willReturn(false);
        $this->recurringRule->method('name')->willReturn('Recurring');

        $results = $this->service->evaluateRules($transaction);

        $this->assertCount(2, $results);

        $qrResult = $results->firstWhere('confidence', 100);
        $this->assertTrue($qrResult['applied']);

        $supplierResult = $results->firstWhere('confidence', 90);
        $this->assertFalse($supplierResult['applied']);
    }

    public function test_run_catches_rule_exceptions_gracefully(): void
    {
        Config::set('features.rule_engine', true);

        $transaction = $this->createMock(BankTransaction::class);
        $transaction->is_reconciled = false;
        $transaction->id = 42;

        $this->qrRule->method('matches')->willThrowException(new \RuntimeException('DB error'));
        $this->qrRule->method('name')->willReturn('QR');

        $this->supplierRule->method('matches')->willReturn(true);
        $this->supplierRule->method('confidence')->willReturn(90);
        $this->supplierRule->method('name')->willReturn('Supplier');

        $this->recurringRule->method('matches')->willReturn(false);
        $this->recurringRule->method('name')->willReturn('Recurring');

        // Should not throw — the QR rule exception is caught and logged
        $results = $this->service->evaluateRules($transaction);

        // Only supplier matched
        $this->assertCount(1, $results);
        $this->assertEquals(90, $results->first()['confidence']);
    }
}
