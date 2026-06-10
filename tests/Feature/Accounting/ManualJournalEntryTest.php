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

    public function test_update_modifies_draft_entry(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-DRAFT-UPD',
            'description' => 'Original description',
            'is_posted' => false,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $draft = JournalEntry::where('reference', 'JE-DRAFT-UPD')->firstOrFail();

        $response = $this->actAsOrg()->put("/accounting/journal-entries/{$draft->id}", [
            'date' => '2026-03-20',
            'reference' => 'JE-DRAFT-UPDATED',
            'description' => 'Modified description',
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '200.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '200.00'],
            ],
        ]);

        $response->assertRedirect('/accounting/journal-entries');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $draft->id,
            'reference' => 'JE-DRAFT-UPDATED',
            'description' => 'Modified description',
            'date' => '2026-03-20',
        ]);

        $this->assertDatabaseHas('transaction_lines', [
            'journal_entry_id' => $draft->id,
            'account_id' => $this->bank->id,
            'debit' => '200.00',
        ]);
    }

    public function test_update_blocks_posted_entry(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-POSTED-UPD',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $entry = JournalEntry::where('reference', 'JE-POSTED-UPD')->firstOrFail();

        $response = $this->actAsOrg()->put("/accounting/journal-entries/{$entry->id}", [
            'date' => '2026-03-20',
            'reference' => 'JE-MODIFIED',
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '200.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '200.00'],
            ],
        ]);

        $response->assertForbidden();
        $this->assertDatabaseHas('journal_entries', [
            'id' => $entry->id,
            'reference' => 'JE-POSTED-UPD', // unchanged
        ]);
    }

    public function test_post_converts_draft_to_posted(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-TO-POST',
            'is_posted' => false,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '150.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '150.00'],
            ],
        ]);

        $draft = JournalEntry::where('reference', 'JE-TO-POST')->firstOrFail();

        $response = $this->actAsOrg()->post("/accounting/journal-entries/{$draft->id}/post");

        $response->assertRedirect('/accounting/journal-entries');
        $response->assertSessionHas('success');

        $this->assertDatabaseHas('journal_entries', [
            'id' => $draft->id,
            'is_posted' => true,
        ]);
    }

    public function test_post_blocks_already_posted_entry(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-ALREADY-POSTED',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $entry = JournalEntry::where('reference', 'JE-ALREADY-POSTED')->firstOrFail();

        $response = $this->actAsOrg()->post("/accounting/journal-entries/{$entry->id}/post");
        $response->assertForbidden();
    }

    public function test_reverse_creates_draft_reversal(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-TO-REVERSE',
            'description' => 'Original entry',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '300.00', 'credit' => '0', 'description' => 'Bank deposit'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '300.00', 'description' => 'Revenue earned'],
            ],
        ]);

        $original = JournalEntry::where('reference', 'JE-TO-REVERSE')->firstOrFail();

        $response = $this->actAsOrg()->post("/accounting/journal-entries/{$original->id}/reverse");

        $response->assertRedirect('/accounting/journal-entries');
        $response->assertSessionHas('success');

        // Reversal should exist as a DRAFT
        $this->assertDatabaseHas('journal_entries', [
            'reference' => 'REV-JE-TO-REVERSE',
            'is_posted' => false, // Key assertion: it's a draft
        ]);

        $reversal = JournalEntry::where('reference', 'REV-JE-TO-REVERSE')->firstOrFail();

        // Verify lines are swapped (debit ↔ credit)
        $this->assertDatabaseHas('transaction_lines', [
            'journal_entry_id' => $reversal->id,
            'account_id' => $this->bank->id,
            'debit' => '0',
            'credit' => '300.00',
        ]);

        $this->assertDatabaseHas('transaction_lines', [
            'journal_entry_id' => $reversal->id,
            'account_id' => $this->revenue->id,
            'debit' => '300.00',
            'credit' => '0',
        ]);

        // Original entry should remain unchanged
        $this->assertDatabaseHas('journal_entries', [
            'id' => $original->id,
            'reference' => 'JE-TO-REVERSE',
            'is_posted' => true,
        ]);
    }

    public function test_reverse_blocks_draft_entry(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-DRAFT-REV',
            'is_posted' => false,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $draft = JournalEntry::where('reference', 'JE-DRAFT-REV')->firstOrFail();

        $response = $this->actAsOrg()->post("/accounting/journal-entries/{$draft->id}/reverse");
        $response->assertForbidden();
    }

    public function test_reverse_prevents_duplicate_reversals(): void
    {
        $this->actAsOrg()->post('/accounting/journal-entries', [
            'date' => '2026-03-15',
            'reference' => 'JE-DOUBLE-REV',
            'is_posted' => true,
            'lines' => [
                ['account_id' => $this->bank->id, 'debit' => '100.00', 'credit' => '0'],
                ['account_id' => $this->revenue->id, 'debit' => '0', 'credit' => '100.00'],
            ],
        ]);

        $entry = JournalEntry::where('reference', 'JE-DOUBLE-REV')->firstOrFail();

        // First reversal should succeed
        $this->actAsOrg()->post("/accounting/journal-entries/{$entry->id}/reverse");
        $this->assertDatabaseHas('journal_entries', ['reference' => 'REV-JE-DOUBLE-REV']);

        // Second reversal should fail (duplicate reference)
        $response = $this->actAsOrg()->post("/accounting/journal-entries/{$entry->id}/reverse");
        $response->assertSessionHas('error');
    }
}
