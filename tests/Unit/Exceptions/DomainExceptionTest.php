<?php

namespace Tests\Unit\Exceptions;

use App\Domains\Accounting\Exceptions\FeatureDisabledException;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use PHPUnit\Framework\TestCase;

class DomainExceptionTest extends TestCase
{
    public function test_already_reconciled_has_default_message(): void
    {
        $exception = new AlreadyReconciledException();

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertEquals('Transaction is already reconciled.', $exception->getMessage());
    }

    public function test_already_reconciled_accepts_custom_message(): void
    {
        $exception = new AlreadyReconciledException('Custom message');

        $this->assertEquals('Custom message', $exception->getMessage());
    }

    public function test_feature_disabled_includes_feature_name(): void
    {
        $exception = new FeatureDisabledException('auto_reconciliation');

        $this->assertInstanceOf(\DomainException::class, $exception);
        $this->assertEquals('Feature [auto_reconciliation] is not enabled.', $exception->getMessage());
    }

    public function test_feature_disabled_with_rule_engine(): void
    {
        $exception = new FeatureDisabledException('rule_engine');

        $this->assertStringContainsString('rule_engine', $exception->getMessage());
    }
}
