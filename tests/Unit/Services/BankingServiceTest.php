<?php

namespace Tests\Unit\Services;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Banking\DTOs\RecordBankTransactionData;
use App\Domains\Banking\Enums\BankTransactionType;
use App\Domains\Banking\Exceptions\UnlinkedBankAccountException;
use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Models\BankTransaction;
use App\Domains\Banking\Services\BankingService;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BankingServiceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Account $bankLedgerAccount;

    private Account $revenueAccount;

    private BankAccount $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Bank Test Org',
            'currency' => 'CHF',
        ]);

        $this->bankLedgerAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->revenueAccount = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $this->bankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => $this->bankLedgerAccount->id,
            'name' => 'Main Bank',
            'currency' => 'CHF',
            'balance' => 1000.00,
        ]);
    }

    public function test_record_transaction_posts_credit_entry_and_updates_balance(): void
    {
        $service = app(BankingService::class);

        $transaction = $service->recordTransaction($this->bankAccount, new RecordBankTransactionData(
            date: '2026-03-20',
            amount: '-250.00',
            type: BankTransactionType::Credit,
            description: 'Customer payment',
            reference: null,
            contraAccountCode: $this->revenueAccount->code,
        ));

        $transaction->refresh();

        $this->assertSame('250.00', $transaction->amount);
        $this->assertNotNull($transaction->journal_entry_id);
        $this->assertTrue($transaction->journalEntry->isBalanced());
        $this->assertSame('1250.00', $this->bankAccount->fresh()->balance);
        $this->assertCount(2, $transaction->journalEntry->lines);
    }

    public function test_post_bank_transaction_requires_linked_ledger_account(): void
    {
        $service = app(BankingService::class);

        $unlinkedBankAccount = BankAccount::create([
            'organization_id' => $this->organization->id,
            'account_id' => null,
            'name' => 'Unlinked',
            'currency' => 'CHF',
            'balance' => 100.00,
        ]);

        $transaction = BankTransaction::create([
            'bank_account_id' => $unlinkedBankAccount->id,
            'date' => '2026-03-20',
            'description' => 'Manual deposit',
            'amount' => 50.00,
            'type' => BankTransactionType::Credit,
            'reference' => 'TX-1',
        ]);

        $this->expectException(UnlinkedBankAccountException::class);

        $service->postBankTransaction($transaction, $this->revenueAccount->code);
    }
}
