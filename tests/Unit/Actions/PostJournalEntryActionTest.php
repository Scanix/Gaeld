<?php

namespace Tests\Unit\Actions;

use App\Domains\Accounting\Actions\PostJournalEntryAction;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;
use Mockery;
use Tests\TestCase;

class PostJournalEntryActionTest extends TestCase
{
    private PostJournalEntryAction $action;

    private $ledgerService;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ledgerService = Mockery::mock(LedgerService::class);
        $this->action = new PostJournalEntryAction($this->ledgerService);
    }

    public function test_execute_delegates_to_ledger_service(): void
    {
        $orgId = 'org-1';
        $entryData = ['date' => '2024-01-01', 'reference' => 'JE-001', 'description' => 'Test'];
        $lines = [
            ['account_id' => 1, 'debit' => 100, 'credit' => 0],
            ['account_id' => 2, 'debit' => 0, 'credit' => 100],
        ];

        $journalEntry = Mockery::mock(JournalEntry::class);

        $this->ledgerService
            ->shouldReceive('postEntry')
            ->once()
            ->with($orgId, $entryData, $lines)
            ->andReturn($journalEntry);

        $result = $this->action->execute($orgId, $entryData, $lines);

        $this->assertSame($journalEntry, $result);
    }

    public function test_post_draft_delegates_to_ledger_service(): void
    {
        $entry = Mockery::mock(JournalEntry::class);
        $postedEntry = Mockery::mock(JournalEntry::class);

        $this->ledgerService
            ->shouldReceive('postDraft')
            ->once()
            ->with($entry)
            ->andReturn($postedEntry);

        $result = $this->action->postDraft($entry);

        $this->assertSame($postedEntry, $result);
    }
}
