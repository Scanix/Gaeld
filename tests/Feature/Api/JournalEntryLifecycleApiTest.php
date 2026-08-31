<?php

namespace Tests\Feature\Api;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Enums\FiscalYearStatus;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Services\CurrentOrganization;
use Tests\Security\SecurityTestCase;

class JournalEntryLifecycleApiTest extends SecurityTestCase
{
    private string $tokenA;

    protected function setUp(): void
    {
        parent::setUp();

        config(['features.api_access' => true]);
        app(CurrentOrganization::class)->set($this->orgA);
        $this->createAccount('1020', 'Bank', AccountType::Asset->value);
        $this->createAccount('3000', 'Revenue', AccountType::Revenue->value);
        $this->tokenA = $this->createApiToken($this->ownerA, $this->orgA);
    }

    public function test_it_lists_entries_with_status_and_date_filters(): void
    {
        $this->createEntry('LIFE-POSTED', 'posted', '2026-08-21');
        $this->createEntry('LIFE-DRAFT', 'draft', '2026-08-20');

        $response = $this->withToken($this->tokenA)
            ->getJson('/api/v1/journal-entries?status=posted&from=2026-08-21&to=2026-08-21');

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.reference', 'LIFE-POSTED')
            ->assertJsonPath('meta.per_page', 20);
    }

    public function test_it_posts_a_draft_once(): void
    {
        $entry = $this->createEntry('LIFE-TO-POST', 'draft', '2026-08-21');

        $response = $this->withToken($this->tokenA)
            ->postJson("/api/v1/journal-entries/{$entry->id}/post");

        $response->assertOk()->assertJsonPath('data.status', 'posted');
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'is_posted' => true,
        ]);

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/journal-entries/{$entry->id}/post")
            ->assertOk()
            ->assertJsonPath('data.id', $entry->id);

        $this->withToken($this->tokenA)
            ->withHeader('Idempotency-Key', 'post-already-completed')
            ->postJson("/api/v1/journal-entries/{$entry->id}/post")
            ->assertStatus(409)
            ->assertJsonPath('code', 'concurrent_transition');
    }

    public function test_it_reverses_a_posted_entry_without_editing_the_original(): void
    {
        $entry = $this->createEntry('LIFE-REVERSIBLE', 'posted', '2026-08-21');

        $response = $this->withToken($this->tokenA)
            ->postJson("/api/v1/journal-entries/{$entry->id}/reverse", [
                'description' => 'Correction from external system',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.reference', 'REV-LIFE-REVERSIBLE');
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'reference' => 'LIFE-REVERSIBLE',
            'is_posted' => true,
        ]);

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/journal-entries/{$entry->id}/reverse", [
                'description' => 'Correction from external system',
            ])
            ->assertOk()
            ->assertJsonPath('data.reference', 'REV-LIFE-REVERSIBLE');
    }

    public function test_it_deletes_a_draft_only(): void
    {
        $entry = $this->createEntry('LIFE-DELETE', 'draft', '2026-08-21');

        $response = $this->withToken($this->tokenA)
            ->deleteJson("/api/v1/journal-entries/{$entry->id}");

        $response->assertNoContent();
        $this->assertDatabaseMissing('journal_entries', ['id' => $entry->id]);
    }

    public function test_publishing_a_draft_in_a_closed_fiscal_year_is_rejected(): void
    {
        FiscalYear::create([
            'organization_id' => $this->orgA->id,
            'name' => 'Closed 2025',
            'start_date' => '2025-01-01',
            'end_date' => '2025-12-31',
            'status' => FiscalYearStatus::Closed,
        ]);
        $entry = $this->createEntry('LIFE-CLOSED', 'draft', '2025-06-30');

        $this->withToken($this->tokenA)
            ->postJson("/api/v1/journal-entries/{$entry->id}/post")
            ->assertStatus(422)
            ->assertJsonPath('code', 'fiscal_year_closed');
    }

    private function createEntry(string $reference, string $status, string $date): JournalEntry
    {
        $response = $this->withToken($this->tokenA)->postJson('/api/v1/journal-entries', [
            'date' => $date,
            'reference' => $reference,
            'description' => $reference,
            'status' => $status,
            'lines' => [
                ['account_code' => '1020', 'debit' => '100.00', 'credit' => '0.00'],
                ['account_code' => '3000', 'debit' => '0.00', 'credit' => '100.00'],
            ],
        ]);

        $response->assertCreated();

        return JournalEntry::query()->whereKey($response->json('data.id'))->firstOrFail();
    }

    private function createAccount(string $code, string $name, string $type): void
    {
        Account::create([
            'organization_id' => $this->orgA->id,
            'code' => $code,
            'name' => $name,
            'type' => $type,
        ]);
    }
}
