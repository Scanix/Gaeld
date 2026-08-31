<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

class JournalEntryApiTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);

        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        Account::create([
            'organization_id' => $this->orgB->id,
            'code' => '1999',
            'name' => 'Other organization account',
            'type' => AccountType::Asset->value,
        ]);

        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_it_creates_a_posted_entry_from_account_codes(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => '2026-08-21',
            'reference' => 'LOG-2026-0001',
            'description' => 'Membership income',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1020', 'debit' => '500.00', 'credit' => '0.00', 'description' => 'Bank'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '500.00', 'description' => 'Income'],
            ],
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.status', 'posted')
            ->assertJsonPath('data.reference', 'LOG-2026-0001')
            ->assertJsonPath('data.lines.0.account_code', '1020')
            ->assertJsonPath('data.lines.1.account_code', '3000');

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->orgA->id,
            'reference' => 'LOG-2026-0001',
            'is_posted' => true,
        ]);
        $this->assertDatabaseHas('journal_events', [
            'organization_id' => $this->orgA->id,
            'event_type' => 'posted',
        ]);
        $this->assertSame(1, JournalEntry::where('organization_id', $this->orgA->id)->count());
    }

    public function test_it_creates_a_draft_without_affecting_posted_balances(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => '2026-08-21',
            'reference' => 'LOG-2026-DRAFT',
            'description' => 'Draft income',
            'status' => 'draft',
            'lines' => [
                ['account_code' => '1020', 'debit' => '250.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '250.00'],
            ],
        ]);

        $response->assertCreated()->assertJsonPath('data.status', 'draft');
        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->orgA->id,
            'reference' => 'LOG-2026-DRAFT',
            'is_posted' => false,
        ]);
    }

    public function test_it_rejects_an_unbalanced_entry_without_persisting_it(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => '2026-08-21',
            'reference' => 'LOG-2026-UNBALANCED',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1020', 'debit' => '500.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '400.00'],
            ],
        ]);

        $response->assertStatus(422)->assertJsonPath('message', 'The journal entry is not balanced.');
        $this->assertDatabaseMissing('journal_entries', [
            'organization_id' => $this->orgA->id,
            'reference' => 'LOG-2026-UNBALANCED',
        ]);
    }

    public function test_it_rejects_an_account_code_from_another_organization(): void
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => '2026-08-21',
            'reference' => 'LOG-2026-CROSS-ORG',
            'status' => 'posted',
            'lines' => [
                ['account_code' => '1999', 'debit' => '500.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '500.00'],
            ],
        ]);

        $response->assertStatus(422);
        $this->assertDatabaseMissing('journal_entries', [
            'organization_id' => $this->orgA->id,
            'reference' => 'LOG-2026-CROSS-ORG',
        ]);
    }

    public function test_it_rejects_invalid_list_filters(): void
    {
        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/journal-entries?status=invalid&per_page=101');

        $response->assertStatus(422)
            ->assertJsonPath('code', 'validation_error')
            ->assertJsonStructure(['errors' => ['status', 'per_page']]);
    }
}
