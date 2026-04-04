<?php

namespace Tests\Unit\Exceptions;

use App\Domains\Accounting\Exceptions\AlreadyPostedException;
use App\Domains\Accounting\Exceptions\DuplicateReferenceException;
use App\Domains\Accounting\Exceptions\InvalidEntryDataException;
use App\Domains\Accounting\Exceptions\UnbalancedEntryException;
use App\Domains\Banking\Exceptions\AlreadyReconciledException;
use App\Domains\Banking\Exceptions\ReconciliationFailedException;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Expenses\Exceptions\InvalidExpenseStateException;
use App\Domains\Invoicing\Exceptions\InvalidInvoiceStateException;
use App\Domains\Invoicing\Exceptions\InvalidPaymentException;
use PHPUnit\Framework\TestCase;

class DomainExceptionHierarchyTest extends TestCase
{
    /**
     * All domain exceptions must extend \DomainException so the global handler catches them.
     */
    public function test_all_domain_exceptions_extend_domain_exception(): void
    {
        $exceptions = [
            AlreadyPostedException::class,
            DuplicateReferenceException::class,
            InvalidEntryDataException::class,
            UnbalancedEntryException::class,
            AlreadyReconciledException::class,
            ReconciliationFailedException::class,
            UnlinkedBankAccountException::class,
            InvalidExpenseStateException::class,
            InvalidInvoiceStateException::class,
            InvalidPaymentException::class,
        ];

        foreach ($exceptions as $class) {
            $this->assertTrue(
                is_subclass_of($class, \DomainException::class),
                "{$class} must extend \\DomainException"
            );
        }
    }

    public function test_unlinked_bank_account_has_default_message(): void
    {
        $e = new UnlinkedBankAccountException;
        $this->assertSame('Bank account is not linked to a ledger account.', $e->getMessage());
    }
}
