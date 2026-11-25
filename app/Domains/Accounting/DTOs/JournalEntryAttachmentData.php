<?php

namespace App\Domains\Accounting\DTOs;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryAttachment;

final class JournalEntryAttachmentData
{
    /**
     * @param  iterable<JournalEntryAttachment>  $attachments
     * @return list<array{id: string, filename: string, added_at: string|null, added_by: string|null, show_url: string, download_url: string, remove_url: string|null}>
     */
    public static function forEntry(JournalEntry $entry, iterable $attachments, bool $canWithdraw): array
    {
        $items = [];

        foreach ($attachments as $attachment) {
            $parameters = ['journalEntry' => $entry, 'attachment' => $attachment];

            $items[] = [
                'id' => $attachment->id,
                'filename' => $attachment->original_filename,
                'added_at' => $attachment->created_at?->toISOString(),
                'added_by' => $attachment->user?->name,
                'show_url' => route('accounting.journal-entries.attachments.show', $parameters),
                'download_url' => route('accounting.journal-entries.attachments.download', $parameters),
                'remove_url' => $canWithdraw ? route('accounting.journal-entries.attachments.destroy', $parameters) : null,
            ];
        }

        return $items;
    }
}
