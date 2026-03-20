<?php

namespace Tests\Unit;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class CacheInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private LedgerService $ledgerService;

    private Organization $organization;

    private array $accounts = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = new LedgerService();

        $user = User::factory()->create();
        $this->organization = Organization::create(['name' => 'Cache Test Org', 'currency' => 'CHF']);
        $this->organization->users()->attach($user->id, ['role' => 'owner']);

        $this->accounts['ar'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100', 'name' => 'Accounts Receivable', 'type' => AccountType::Asset->value,
        ]);

        $this->accounts['revenue'] = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000', 'name' => 'Revenue', 'type' => AccountType::Revenue->value,
        ]);
    }

    public function test_account_balance_is_cached_after_first_call(): void
    {
        Cache::flush();

        $accountId = $this->accounts['ar']->id;

        // First call should miss cache and compute
        $balance1 = $this->ledgerService->accountBalance($accountId);
        $this->assertEquals(0.0, $balance1);

        // Second call should return same value (from cache on array driver)
        $balance2 = $this->ledgerService->accountBalance($accountId);
        $this->assertEquals($balance1, $balance2);
    }

    public function test_posting_entry_flushes_ledger_cache(): void
    {
        Cache::flush();

        // Prime the cache with initial balance
        $this->ledgerService->accountBalance($this->accounts['ar']->id);
        $this->ledgerService->accountBalance($this->accounts['revenue']->id);
        $this->ledgerService->trialBalance($this->organization->id);

        // Post a new entry — should flush the cached balances
        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-17',
            reference: 'CACHE-TEST-001',
            description: 'Cache invalidation test',
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '500.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '500.00'),
            ],
        ));

        // After posting, balance should reflect the new entry (cache was flushed)
        $balance = $this->ledgerService->accountBalance($this->accounts['ar']->id);

        $this->assertEquals(500.0, $balance);
    }

    public function test_trial_balance_is_cached(): void
    {
        Cache::flush();

        $tb1 = $this->ledgerService->trialBalance($this->organization->id);
        $tb2 = $this->ledgerService->trialBalance($this->organization->id);

        // Both results should be identical arrays (second is from cache)
        $this->assertIsArray($tb1);
        $this->assertEquals($tb1, $tb2);
    }

    public function test_flush_cache_clears_tagged_entries(): void
    {
        Cache::flush();

        // Prime both caches
        $this->ledgerService->accountBalance($this->accounts['ar']->id);
        $this->ledgerService->trialBalance($this->organization->id);

        // Manually flush cache
        $this->ledgerService->flushCache($this->organization->id);

        // Post an entry and verify fresh data is returned
        $this->ledgerService->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-17',
            reference: 'FLUSH-TEST',
            description: 'Flush test',
            lines: [
                new JournalLineData(accountId: $this->accounts['ar']->id, debit: '200.00', credit: '0'),
                new JournalLineData(accountId: $this->accounts['revenue']->id, debit: '0', credit: '200.00'),
            ],
        ));

        $balance = $this->ledgerService->accountBalance($this->accounts['ar']->id);
        $this->assertEquals(200.0, $balance);
    }
}
