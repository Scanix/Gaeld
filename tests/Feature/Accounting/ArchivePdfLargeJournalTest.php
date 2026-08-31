<?php

namespace Tests\Feature\Accounting;

use App\Domains\Accounting\Actions\GenerateArchivePdfAction;
use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\LegalArchive;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Traits\WithAuthenticatedOrganization;

class ArchivePdfLargeJournalTest extends TestCase
{
    use RefreshDatabase, WithAuthenticatedOrganization;

    public function test_journal_pdf_generation_handles_a_large_entry_set_with_limited_memory(): void
    {
        $this->setUpOrganization();
        Storage::fake('local');

        $bank = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '1020',
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $revenue = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '3000',
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        for ($index = 1; $index <= 1000; $index++) {
            $entry = JournalEntry::create([
                'organization_id' => $this->organization->id,
                'date' => sprintf('2024-%02d-01', (($index - 1) % 12) + 1),
                'reference' => "LARGE-{$index}",
                'description' => 'Large journal entry',
                'is_posted' => true,
            ]);

            $entry->lines()->createMany([
                ['account_id' => $bank->id, 'debit' => '100.00', 'credit' => '0.00'],
                ['account_id' => $revenue->id, 'debit' => '0.00', 'credit' => '100.00'],
            ]);
        }

        $results = app(GenerateArchivePdfAction::class)->execute($this->organization->id, 2024);
        $journal = LegalArchive::query()
            ->where('organization_id', $this->organization->id)
            ->where('document_type', 'pdf_journal')
            ->firstOrFail();

        $this->assertCount(3, $results);
        $this->assertTrue(Storage::exists($journal->storage_path));
        $this->assertStringStartsWith('%PDF-', Storage::get($journal->storage_path));
    }
}
