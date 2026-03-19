<?php

namespace Tests\Unit\Services;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Accounting\Services\LedgerService;
use Mockery;
use Tests\TestCase;

class BankingServiceTest extends TestCase
{
    private BankingService $service;

    private $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->service = new BankingService($this->ledgerService);
    }

    public function test_update_bank_account_balance_deposit(): void
    {
        $bankAccount = Mockery::mock(BankAccount::class)->makePartial();
        $bankAccount->balance = '1000.00';

        $bankAccount->shouldReceive('update')
            ->once()
            ->with(['balance' => '1250.00']);

        $this->service->updateBankAccountBalance($bankAccount, '250.00', true);
    }

    public function test_update_bank_account_balance_withdrawal(): void
    {
        $bankAccount = Mockery::mock(BankAccount::class)->makePartial();
        $bankAccount->balance = '1000.00';

        $bankAccount->shouldReceive('update')
            ->once()
            ->with(['balance' => '750.00']);

        $this->service->updateBankAccountBalance($bankAccount, '250.00', false);
    }

    public function test_update_bank_account_balance_to_zero(): void
    {
        $bankAccount = Mockery::mock(BankAccount::class)->makePartial();
        $bankAccount->balance = '500.00';

        $bankAccount->shouldReceive('update')
            ->once()
            ->with(['balance' => '0.00']);

        $this->service->updateBankAccountBalance($bankAccount, '500.00', false);
    }
}
