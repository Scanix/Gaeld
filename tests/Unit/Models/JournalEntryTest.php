<?php

namespace Tests\Unit\Models;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class JournalEntryTest extends TestCase
{
    use RefreshDatabase;

    private Organization $organization;

    private Account $assetAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organization = Organization::create([
            'name' => 'Journal Entry Org',
            'currency' => 'CHF',
            'country' => 'CH',
        ]);

        $this->assetAccount = Account::create([
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
    }

    public function test_it_casts_date_and_posted_flag(): void
    {
        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-03-20',
            'reference' => 'JE-CASTS',
            'description' => 'Casting test',
            'is_posted' => 1,
        ]);

        $this->assertInstanceOf(Carbon::class, $entry->date);
        $this->assertTrue($entry->is_posted);
    }

    public function test_is_balanced_returns_true_when_debits_equal_credits(): void
    {
        $entry = $this->createEntry([
            [
                'account_id' => $this->assetAccount->id,
                'debit' => '250.00',
                'credit' => '0.00',
                'description' => 'Bank receipt',
            ],
            [
                'account_id' => $this->revenueAccount->id,
                'debit' => '0.00',
                'credit' => '250.00',
                'description' => 'Revenue recognition',
            ],
        ]);

        $this->assertTrue($entry->isBalanced());
        $this->assertSame('250.00', $entry->totalDebit());
        $this->assertSame('250.00', $entry->totalCredit());
    }

    public function test_is_balanced_returns_false_when_totals_do_not_match(): void
    {
        $entry = $this->createEntry([
            [
                'account_id' => $this->assetAccount->id,
                'debit' => '250.00',
                'credit' => '0.00',
                'description' => 'Bank receipt',
            ],
            [
                'account_id' => $this->revenueAccount->id,
                'debit' => '0.00',
                'credit' => '200.00',
                'description' => 'Revenue recognition',
            ],
        ]);

        $this->assertFalse($entry->isBalanced());
        $this->assertSame('250.00', $entry->totalDebit());
        $this->assertSame('200.00', $entry->totalCredit());
    }

    /**
     * @param array<int, array<string, string>> $lines
     */
    private function createEntry(array $lines): JournalEntry
    {
        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-03-20',
            'reference' => 'JE-1',
            'description' => 'Journal entry test',
            'is_posted' => true,
        ]);

        foreach ($lines as $line) {
            TransactionLine::create([
                'journal_entry_id' => $entry->id,
                'account_id' => $line['account_id'],
                'debit' => $line['debit'],
                'credit' => $line['credit'],
                'description' => $line['description'],
            ]);
        }

        return $entry;
    }
}