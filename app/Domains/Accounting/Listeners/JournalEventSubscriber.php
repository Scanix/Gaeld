<?php

namespace App\Domains\Accounting\Listeners;

use App\Domains\Accounting\Events\JournalDraftCreated;
use App\Domains\Accounting\Events\JournalDraftPosted;
use App\Domains\Accounting\Events\JournalEntryPosted;
use App\Domains\Accounting\Events\JournalEntryReversed;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEvent;
use Illuminate\Contracts\Events\Dispatcher;

class JournalEventSubscriber
{
    public function handlePosted(JournalEntryPosted $event): void
    {
        $this->store($event->journalEntry, 'posted', [
            'reference' => $event->journalEntry->reference,
            'total_debit' => $event->journalEntry->totalDebit(),
            'line_count' => $event->journalEntry->lines->count(),
        ]);
    }

    public function handleDraftCreated(JournalDraftCreated $event): void
    {
        $this->store($event->journalEntry, 'draft_created', [
            'reference' => $event->journalEntry->reference,
        ]);
    }

    public function handleDraftPosted(JournalDraftPosted $event): void
    {
        $this->store($event->journalEntry, 'draft_posted', [
            'reference' => $event->journalEntry->reference,
        ]);
    }

    public function handleReversed(JournalEntryReversed $event): void
    {
        $this->store($event->reversalEntry, 'reversed', [
            'original_entry_id' => $event->originalEntry->id,
            'original_reference' => $event->originalEntry->reference,
            'reversal_reference' => $event->reversalEntry->reference,
        ]);
    }

    public function subscribe(Dispatcher $events): void
    {
        $events->listen(JournalEntryPosted::class, [self::class, 'handlePosted']);
        $events->listen(JournalDraftCreated::class, [self::class, 'handleDraftCreated']);
        $events->listen(JournalDraftPosted::class, [self::class, 'handleDraftPosted']);
        $events->listen(JournalEntryReversed::class, [self::class, 'handleReversed']);
    }

    /** @param array<string, mixed> $payload */
    private function store(JournalEntry $journalEntry, string $eventType, array $payload): void
    {
        JournalEvent::create([
            'journal_entry_id' => $journalEntry->id,
            'organization_id' => $journalEntry->organization_id,
            'event_type' => $eventType,
            'payload' => $payload,
            'occurred_at' => now(),
        ]);
    }
}
