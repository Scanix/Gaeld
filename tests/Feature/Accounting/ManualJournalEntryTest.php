<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ManualJournalEntryTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    private Account $bank;

    private Account $revenue;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpOrganization();

        $this->bank = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);

        $this->revenue = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);
    }

    public function test_create_form_renders_with_accounts(): void
    {
        $response = $this->actAsOrg()->get('/accounting/journal-entries/create');

        $response->assertOk();
        $response->assertInertia(fn ($page) => $page
            ->component('Accounting/JournalEntryCreate')
            ->has('accounts', 2));
    }

    public function test_store_posts_a_balanced_entry(): void
    {
        $response = $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-MAN-1',
            'description' => 'Manual entry',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '250.00', 'credit' => '0', 'description' => ''],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '250.00', 'description' => ''],
            ],
        ]);

        $response->assertRedirect('/accounting/journal-entries');

        $this->assertDatabaseHas('journal_entries', [
            'organization_id' => $this->organization->id,
            'reference' => 'JE-MAN-1',
            'is_posted' => true,
        ]);
    }

    public function test_store_can_save_as_draft(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-DRAFT-1',
            'description' => 'Draft',
            'is_posted' => false,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0', 'description' => ''],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00', 'description' => ''],
            ],
        ]);

        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'JE-DRAFT-1',
            'is_posted' => false,
        ]);
    }

    public function test_store_rejects_unbalanced_entry(): void
    {
        $response = $this->actAsOrg()->from('/accounting/journal-entries/create')->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-UNBAL',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '50.00'],
            ],
        ]);

        $response->assertRedirect('/accounting/journal-entries/create');
        $response->assertSessionHas('error');
        $this->assertDatabaseMissing('journal_entries', ['reference' => 'JE-UNBAL']);
    }

    public function test_store_validates_account_belongs_to_organization(): void
    {
        $foreignOrg = Organization::factory()->create();
        $foreignAccount = Account::create([
            'organization_id' => $foreignOrg->id,
            'code' => '1999',
            'name' => 'Foreign',
            'type' => AccountType::Asset->value,
        ]);

        $response = $this->actAsOrg()->from('/accounting/journal-entries/create')->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $foreignAccount->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $response->assertSessionHasErrors('lines.0.account_id');
    }

    public function test_destroy_deletes_draft_only(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-DEL',
            'is_posted' => false,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $draft = JournalEntry::where('reference', 'JE-DEL')->firstOrFail();

        $response = $this->actAsOrg()->delete("/accounting/journal-entries/{$draft->id}");
        $response->assertRedirect('/accounting/journal-entries');
        $this->assertDatabaseMissing('journal_entries', ['id' => $draft->id]);
    }

    public function test_destroy_blocks_posted_entry(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-POSTED',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $entry = JournalEntry::where('reference', 'JE-POSTED')->firstOrFail();

        $response = $this->actAsOrg()->delete("/accounting/journal-entries/{$entry->id}");
        $response->assertForbidden();
        $this->assertDatabaseHas('journal_entries', ['id' => $entry->id]);
    }
}
