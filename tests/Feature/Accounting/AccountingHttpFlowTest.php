<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\CreatesAccountingFixtures;
use Tests\Traits\WithAuthenticatedOrganization;

class AccountingHttpFlowTest extends TestCase
{
    use CreatesAccountingFixtures, RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bankAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->bankAccount = Account::create([
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

    public function test_chart_of_accounts_route_returns_current_organization_accounts(): void
    {
        Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1100',
            'name' => 'Receivables',
            'type' => AccountType::Asset->value,
        ]);

        Organization::create([
            'name' => 'Other Org',
            'currency' => 'EUR',
        ])->accounts()->create([
            'code' => '9999',
            'name' => 'Other Org Account',
            'type' => AccountType::Asset->value,
        ]);

        $response = $this->actAsOrg()->get('/accounting/chart-of-accounts');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/ChartOfAccounts')
            ->has('accounts.data', 3));
    }

    public function test_journal_entries_route_returns_paginated_posted_entries(): void
    {
        $this->postSampleEntry();

        $response = $this->actAsOrg()->get('/accounting/journal-entries');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/JournalEntries')
            ->has('entries.data', 1));
    }

    public function test_trial_balance_route_returns_balances_from_posted_entries(): void
    {
        $this->postSampleEntry();

        $response = $this->actAsOrg()->get('/accounting/trial-balance?as_of_date=2026-03-31');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/TrialBalance')
            ->where('asOfDate', '2026-03-31')
            ->has('balances', 2)
            ->where('balances.0.account_code', '1020')
            ->where('balances.0.debit', '500.00')
            ->where('balances.1.account_code', '3000')
            ->where('balances.1.credit', '500.00'));
    }

    private function postSampleEntry(): void
    {
        $this->postJournalEntry('2026-03-15', [
            $this->journalLine($this->bankAccount, '500.00', '0.00', 'Bank'),
            $this->journalLine($this->revenueAccount, '0.00', '500.00', 'Revenue'),
        ], 'TB-1', 'Trial balance sample');
    }
}
