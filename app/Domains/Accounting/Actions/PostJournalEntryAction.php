<?php

namespace App\Domains\Accounting\Actions;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Services\LedgerService;

class PostJournalEntryAction
{
    public function __construct(
        private LedgerService $ledgerService,
    ) {}

    /**
     * Post a new journal entry or post an existing draft.
     */
    public function execute(string $organizationId, array $entryData, array $lines): JournalEntry
    {
        return $this->ledgerService->postEntry($organizationId, $entryData, $lines);
    }

    public function postDraft(JournalEntry $entry): JournalEntry
    {
        return $this->ledgerService->postDraft($entry);
    }
}
