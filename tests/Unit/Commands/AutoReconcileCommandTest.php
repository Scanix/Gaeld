<?php

namespace Tests\Unit\Commands;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AutoReconcileCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_outputs_message_when_no_accounts_have_unreconciled_transactions(): void
    {
        $this->artisan('gaeld:auto-reconcile')
            ->assertSuccessful()
            ->expectsOutputToContain('No bank accounts with unreconciled transactions');
    }
}
