<?php

namespace App\Domains\Accounting\Events;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class JournalDraftCreated
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public readonly JournalEntry $journalEntry,
    ) {}
}
