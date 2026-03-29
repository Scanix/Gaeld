<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\DTOs\JournalEntryData;
use App\Domains\Accounting\DTOs\JournalLineData;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Services\LedgerService;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Users\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithOrganizationPermissions;

class AccountingHttpFlowTest extends TestCase
{
    use RefreshDatabase, WithOrganizationPermissions;

    private User $user;

    private Organization $organization;

    private Account $bankAccount;

    private Account $revenueAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedPermissions();

        $this->user = User::factory()->create();
        $this->organization = Organization::create([
            'name' => 'Accounting HTTP Org',
            'currency' => 'CHF',
        ]);
        $this->organization->users()->attach($this->user->id, ['role' => 'owner']);
        $this->assignOrganizationRole($this->user, $this->organization, 'owner');

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

        $response = $this->asCurrentOrg()->get('/accounting/chart-of-accounts');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/ChartOfAccounts')
            ->has('accounts', 3));
    }

    public function test_journal_entries_route_returns_paginated_posted_entries(): void
    {
        $this->postSampleEntry();

        $response = $this->asCurrentOrg()->get('/accounting/journal-entries');

        $response->assertStatus(200);
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/JournalEntries')
            ->has('entries.data', 1));
    }

    public function test_trial_balance_route_returns_balances_from_posted_entries(): void
    {
        $this->postSampleEntry();

        $response = $this->asCurrentOrg()->get('/accounting/trial-balance?as_of_date=2026-03-31');

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
        app(LedgerService::class)->postEntry($this->organization->id, new JournalEntryData(
            date: '2026-03-15',
            reference: 'TB-1',
            description: 'Trial balance sample',
            lines: [
                new JournalLineData(accountId: $this->bankAccount->id, debit: '500.00', credit: '0.00', description: 'Bank'),
                new JournalLineData(accountId: $this->revenueAccount->id, debit: '0.00', credit: '500.00', description: 'Revenue'),
            ],
        ));
    }

    private function asCurrentOrg(): self
    {
        return $this->actingAs($this->user)->withSession([
            'current_organization_id' => $this->organization->id,
        ]);
    }
}
