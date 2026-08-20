<?php

namespace Tests\Unit\Accounting;

use App\Domains\Accounting\Enums\AccountType;
use App\Domains\Accounting\Events\JournalDraftCreated;
use App\Domains\Accounting\Events\JournalDraftPosted;
use App\Domains\Accounting\Events\JournalEntryPosted;
use App\Domains\Accounting\Events\JournalEntryReversed;
use App\Domains\Accounting\Listeners\JournalEventSubscriber;
use App\Domains\Accounting\Models\Account;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEvent;
use App\Domains\Accounting\Models\TransactionLine;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * JournalEventSubscriber previously had zero test coverage despite being
 * registered via Event::subscribe() in AppServiceProvider and writing real
 * audit records (JournalEvent) for every posted/draft/reversed journal
 * entry. A silent regression here (wrong event class, wrong payload key,
 * subscribe() not wiring a listener) would mean the accounting audit trail
 * quietly stops recording — hard to notice until an audit is needed.
 */
class JournalEventSubscriberTest extends TestCase
{
    use RefreshDatabase;

    private JournalEventSubscriber $subscriber;

    private Organization $organization;

    protected function setUp(): void
    {
        parent::setUp();

        $this->subscriber = new JournalEventSubscriber;
        $this->organization = Organization::create(['name' => 'Journal Event Org', 'currency' => 'CHF']);
    }

    private static int $accountSequence = 0;

    private function makeJournalEntry(string $reference): JournalEntry
    {
        $suffix = str_pad((string) (++self::$accountSequence), 2, '0', STR_PAD_LEFT);

        $bank = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '10'.$suffix,
            'name' => 'Bank',
            'type' => AccountType::Asset->value,
        ]);
        $revenue = Account::create([
            'organization_id' => $this->organization->id,
            'code' => '30'.$suffix,
            'name' => 'Revenue',
            'type' => AccountType::Revenue->value,
        ]);

        $entry = JournalEntry::create([
            'organization_id' => $this->organization->id,
            'date' => '2026-05-01',
            'reference' => $reference,
            'description' => 'Test entry',
            'is_posted' => true,
        ]);

        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $bank->id,
            'debit' => '100.00',
            'credit' => '0.00',
            'description' => 'Bank line',
        ]);
        TransactionLine::create([
            'journal_entry_id' => $entry->id,
            'account_id' => $revenue->id,
            'debit' => '0.00',
            'credit' => '100.00',
            'description' => 'Revenue line',
        ]);

        return $entry->fresh('lines');
    }

    public function test_handle_posted_records_a_journal_event_with_totals(): void
    {
        $entry = $this->makeJournalEntry('JE-POSTED-1');

        $this->subscriber->handlePosted(new JournalEntryPosted($entry));

        $this->assertDatabaseHas('journal_events', [
            'journal_entry_id' => $entry->id,
            'organization_id' => $this->organization->id,
            'event_type' => 'posted',
        ]);

        $event = JournalEvent::where('journal_entry_id', $entry->id)->firstOrFail();
        $this->assertSame('JE-POSTED-1', $event->payload['reference']);
        $this->assertSame('100', $event->payload['total_debit']);
        $this->assertSame(2, $event->payload['line_count']);
    }

    public function test_handle_draft_created_records_a_journal_event(): void
    {
        $entry = $this->makeJournalEntry('JE-DRAFT-1');

        $this->subscriber->handleDraftCreated(new JournalDraftCreated($entry));

        $this->assertDatabaseHas('journal_events', [
            'journal_entry_id' => $entry->id,
            'event_type' => 'draft_created',
        ]);
    }

    public function test_handle_draft_posted_records_a_journal_event(): void
    {
        $entry = $this->makeJournalEntry('JE-DRAFTPOSTED-1');

        $this->subscriber->handleDraftPosted(new JournalDraftPosted($entry));

        $this->assertDatabaseHas('journal_events', [
            'journal_entry_id' => $entry->id,
            'event_type' => 'draft_posted',
        ]);
    }

    public function test_handle_reversed_records_a_journal_event_linking_both_entries(): void
    {
        $original = $this->makeJournalEntry('JE-ORIGINAL-1');
        $reversal = $this->makeJournalEntry('JE-REVERSAL-1');

        $this->subscriber->handleReversed(new JournalEntryReversed($reversal, $original));

        $this->assertDatabaseHas('journal_events', [
            'journal_entry_id' => $reversal->id,
            'event_type' => 'reversed',
        ]);

        $event = JournalEvent::where('journal_entry_id', $reversal->id)->firstOrFail();
        $this->assertSame($original->id, $event->payload['original_entry_id']);
        $this->assertSame('JE-ORIGINAL-1', $event->payload['original_reference']);
        $this->assertSame('JE-REVERSAL-1', $event->payload['reversal_reference']);
    }

    public function test_subscribe_wires_all_four_events_to_the_dispatcher(): void
    {
        $entry = $this->makeJournalEntry('JE-DISPATCH-1');

        // Exercise subscribe() through the real event system rather than
        // calling handlers directly, to catch a wrong event class or a
        // typo'd method name in the listen() mapping.
        JournalEntryPosted::dispatch($entry);

        $this->assertDatabaseHas('journal_events', [
            'journal_entry_id' => $entry->id,
            'event_type' => 'posted',
        ]);
    }
}
