<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_outputs_message_when_no_accounts_have_unreconciled_transactions(): void
    {
        $result = $this->artisan('gaeld:auto-reconcile');
        $result->assertSuccessful();
        $result->expectsOutputToContain('No bank accounts with unreconciled transactions');
        $this->assertDatabaseCount('bank_accounts', 0);
    }
}
