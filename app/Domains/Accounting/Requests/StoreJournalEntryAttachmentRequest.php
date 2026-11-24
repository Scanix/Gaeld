<?php

namespace App\Domains\Accounting\Requests;

use App\Domains\Accounting\Models\JournalEntry;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalEntryAttachmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $entry = $this->route('journalEntry');

        return $entry instanceof JournalEntry && ($this->user()?->can('addAttachment', $entry) ?? false);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return ['file' => ['bail', 'required', 'file', 'max:'.config('uploads.max_size.document'), 'mimetypes:application/pdf']];
    }
}
