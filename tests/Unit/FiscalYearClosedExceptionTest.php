<?php

namespace Tests\Unit;

use App\Domains\Accounting\Exceptions\FiscalYearClosedException;
use Tests\TestCase;

class FiscalYearClosedExceptionTest extends TestCase
{
    public function test_message_contains_year(): void
    {
        $exception = new FiscalYearClosedException(2025);

        $this->assertSame('Cannot post to closed fiscal year 2025.', $exception->getMessage());
    }
}
