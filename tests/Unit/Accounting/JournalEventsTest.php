<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Events\JournalDraftCreated;
use App\Domains\Accounting\Events\JournalDraftPosted;
use App\Domains\Accounting\Events\JournalEntryPosted;
use App\Domains\Accounting\Events\JournalEntryReversed;
use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * These are thin event DTOs (constructor + readonly public properties), but
 * previously had no test referencing them directly. Locks in the property
 * contract each JournalEventSubscriber handler depends on, so a signature
 * change here is caught at the call site, not only inside the subscriber.
 */
class JournalEventsTest extends TestCase
{
    use RefreshDatabase;

    public function test_journal_draft_created_exposes_the_journal_entry(): void
    {
        $entry = new JournalEntry(['reference' => 'JE-1']);

        $event = new JournalDraftCreated($entry);

        $this->assertSame($entry, $event->journalEntry);
    }

    public function test_journal_draft_posted_exposes_the_journal_entry(): void
    {
        $entry = new JournalEntry(['reference' => 'JE-2']);

        $event = new JournalDraftPosted($entry);

        $this->assertSame($entry, $event->journalEntry);
    }

    public function test_journal_entry_posted_exposes_the_journal_entry(): void
    {
        $entry = new JournalEntry(['reference' => 'JE-3']);

        $event = new JournalEntryPosted($entry);

        $this->assertSame($entry, $event->journalEntry);
    }

    public function test_journal_entry_reversed_exposes_both_entries(): void
    {
        $reversal = new JournalEntry(['reference' => 'JE-REV']);
        $original = new JournalEntry(['reference' => 'JE-ORIG']);

        $event = new JournalEntryReversed($reversal, $original);

        $this->assertSame($reversal, $event->reversalEntry);
        $this->assertSame($original, $event->originalEntry);
    }
}
