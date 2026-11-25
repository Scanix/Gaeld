<?php

namespace App\Domains\Accounting\Controllers;

use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryAttachment;
use App\Domains\Accounting\Requests\StoreJournalEntryAttachmentRequest;
use App\Domains\Accounting\Services\JournalEntryAttachmentService;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\HeaderUtils;

class JournalEntryAttachmentController extends Controller
{
    public function __construct(private readonly JournalEntryAttachmentService $attachments) {}

    public function store(StoreJournalEntryAttachmentRequest $request, JournalEntry $journalEntry): RedirectResponse
    {
        $result = $this->attachments->add($journalEntry, $request->validated('file'), $request->user());

        return to_route('accounting.journal-entries.show', $journalEntry)
            ->with('success', __('app.journal_attachment_'.$result['status']));
    }

    public function show(Request $request, JournalEntry $journalEntry, JournalEntryAttachment $attachment): Response
    {
        return $this->document($request, $journalEntry, $attachment, download: false);
    }

    public function download(Request $request, JournalEntry $journalEntry, JournalEntryAttachment $attachment): Response
    {
        return $this->document($request, $journalEntry, $attachment, download: true);
    }

    public function destroy(Request $request, JournalEntry $journalEntry, JournalEntryAttachment $attachment): RedirectResponse
    {
        $this->attachments->withdraw($journalEntry, $attachment, $request->user());

        return to_route('accounting.journal-entries.show', $journalEntry)->with('success', __('app.journal_attachment_withdrawn'));
    }

    private function document(Request $request, JournalEntry $entry, JournalEntryAttachment $attachment, bool $download): Response
    {
        $bytes = $this->attachments->read($entry, $attachment, $request->user(), $download);
        $filename = $attachment->original_filename;
        if (! str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }
        $fallback = str_replace(['%', '/', '\\'], '_', Str::ascii($filename));

        return response($bytes, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => HeaderUtils::makeDisposition($download ? 'attachment' : 'inline', $filename, $fallback),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
