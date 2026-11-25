<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Accounting\Models\JournalEntry;
use App\Domains\Accounting\Models\JournalEntryAttachment;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Domains\Users\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class JournalEntryAttachmentService
{
    public function __construct(
        private readonly OrganizationDocumentStorageService $storageQuota,
        private readonly ArchiveAccessLog $audit,
    ) {}

    /** @return array{attachment: JournalEntryAttachment, status: 'added'|'duplicate'|'restored'} */
    public function add(JournalEntry $entry, UploadedFile $file, User $actor): array
    {
        Gate::forUser($actor)->authorize('addAttachment', $entry);
        $maxBytes = (int) config('uploads.max_size.document') * 1024;
        if (! $file->isValid() || $file->getSize() > $maxBytes || $file->getMimeType() !== 'application/pdf') {
            throw ValidationException::withMessages(['file' => __('app.journal_attachment_invalid_pdf')]);
        }
        $bytes = $file->getContent();
        if (strlen($bytes) > $maxBytes || preg_match('/\A%PDF-\d\.\d\b/', $bytes) !== 1
            || ! str_ends_with(rtrim($bytes, "\x00\t\r\n "), '%%EOF')) {
            throw ValidationException::withMessages(['file' => __('app.journal_attachment_invalid_pdf')]);
        }
        $hash = hash('sha256', $bytes);
        $name = basename(str_replace('\\', '/', $file->getClientOriginalName()));
        $filename = mb_substr(trim((string) preg_replace('/\p{C}/u', '', $name)), 0, 255) ?: 'document.pdf';

        return DB::transaction(function () use ($entry, $actor, $bytes, $filename, $hash): array {
            $entry = $this->lockedEntry($entry);
            $year = $entry->attachmentFiscalYear(lock: true);
            Gate::forUser($actor)->authorize('addAttachment', $entry);
            $existing = $entry->attachments()->withTrashed()->where('checksum_sha256', $hash)->first();
            if ($existing !== null) {
                $this->originalBytes($entry, $existing);
                if (! $existing->trashed()) {
                    return ['attachment' => $existing, 'status' => 'duplicate'];
                }
                $existing->user_id = $actor->id;
                $existing->created_at = now();
                $existing->restore();
                $this->log($entry, $existing, $actor, 'restored');

                return ['attachment' => $existing, 'status' => 'restored'];
            }

            $this->storageQuota->reserve($entry->organization, strlen($bytes), 'file');
            $versions = IssuedDocumentFile::query()->where('organization_id', $entry->organization_id)
                ->where('document_type', JournalEntryAttachment::DOCUMENT_TYPE)->where('document_id', $entry->id);
            $issuedId = (string) Str::uuid();
            $directory = $this->directory($entry, $hash);
            $path = $directory.'/'.$issuedId.'.pdf';
            $disk = Storage::disk('local');

            // A terminated process may leave bytes without a committed database row.
            // Each attempt owns a distinct path, including its eventual rollback callback.
            foreach ($disk->files($directory) as $orphan) {
                if (str_ends_with($orphan, '.pdf') && Str::isUuid(basename($orphan, '.pdf'))
                    && ! $this->storageQuota->isRetained($entry->organization, $orphan) && ! $disk->delete($orphan)) {
                    throw new \RuntimeException('Cannot remove an uncommitted journal attachment.');
                }
            }
            DB::afterRollBack(static fn () => $disk->delete($path));
            if (! $disk->put($path, $bytes) || ! hash_equals($hash, hash('sha256', $disk->get($path) ?? ''))) {
                throw new \RuntimeException('Cannot preserve the journal attachment.');
            }
            $issued = IssuedDocumentFile::create([
                'id' => $issuedId, 'organization_id' => $entry->organization_id, 'fiscal_year_id' => $year?->id,
                'document_type' => JournalEntryAttachment::DOCUMENT_TYPE, 'document_id' => $entry->id,
                'version' => ((int) $versions->max('version')) + 1, 'storage_path' => $path,
                'checksum_sha256' => $hash, 'created_at' => now(),
            ]);
            $attachment = $entry->attachments()->create([
                'organization_id' => $entry->organization_id, 'issued_document_file_id' => $issued->id,
                'user_id' => $actor->id, 'original_filename' => $filename, 'mime_type' => 'application/pdf',
                'size_bytes' => strlen($bytes), 'checksum_sha256' => $hash,
            ]);
            $this->log($entry, $attachment, $actor, 'added');

            return ['attachment' => $attachment, 'status' => 'added'];
        });
    }

    public function withdraw(JournalEntry $entry, JournalEntryAttachment $attachment, User $actor): void
    {
        DB::transaction(function () use ($entry, $attachment, $actor): void {
            $entry = $this->lockedEntry($entry);
            $entry->attachmentFiscalYear(lock: true);
            $link = $entry->attachments()->where('organization_id', $entry->organization_id)->findOrFail($attachment->id);
            Gate::forUser($actor)->authorize('delete', $link->setRelation('journalEntry', $entry));
            $link->delete();
            $this->log($entry, $link, $actor, 'withdrawn');
        });
    }

    public function read(JournalEntry $entry, JournalEntryAttachment $attachment, User $actor, bool $download): string
    {
        $active = $entry->attachments()->where('organization_id', $entry->organization_id)->findOrFail($attachment->id);
        Gate::forUser($actor)->authorize('view', $active->setRelation('journalEntry', $entry));
        $bytes = $this->originalBytes($entry, $active);
        $this->log($entry, $active, $actor, $download ? 'downloaded' : 'viewed');

        return $bytes;
    }

    private function lockedEntry(JournalEntry $entry): JournalEntry
    {
        $organization = Organization::query()->lockForUpdate()->findOrFail($entry->organization_id);
        $locked = JournalEntry::query()->where('organization_id', $organization->id)->lockForUpdate()->findOrFail($entry->id);

        return $locked->setRelation('organization', $organization);
    }

    private function directory(JournalEntry $entry, string $hash): string
    {
        return 'archives/issued/'.$entry->organization_id.'/'.JournalEntryAttachment::DOCUMENT_TYPE.'/'.$entry->id.'/'.$hash;
    }

    private function originalBytes(JournalEntry $entry, JournalEntryAttachment $attachment): string
    {
        $issued = $attachment->issuedDocumentFile;
        $expected = $this->directory($entry, $attachment->checksum_sha256).'/'.$attachment->issued_document_file_id.'.pdf';
        if ($issued === null || $issued->organization_id !== $entry->organization_id || $issued->document_id !== $entry->id
            || $issued->document_type !== JournalEntryAttachment::DOCUMENT_TYPE || $issued->storage_path !== $expected
            || ! hash_equals($attachment->checksum_sha256, $issued->checksum_sha256)) {
            throw new ConflictHttpException(__('app.journal_attachment_integrity_error'));
        }
        $bytes = Storage::disk('local')->get($expected);
        if ($bytes === null || ! hash_equals($attachment->checksum_sha256, hash('sha256', $bytes))) {
            throw new ConflictHttpException(__('app.journal_attachment_integrity_error'));
        }

        return $bytes;
    }

    private function log(JournalEntry $entry, JournalEntryAttachment $attachment, User $actor, string $event): void
    {
        $this->audit->record($entry->organization_id, null, 'journal_attachment.'.$event, $actor, [
            'journal_entry_id' => $entry->id, 'journal_entry_reference' => $entry->reference,
            'attachment_id' => $attachment->id, 'issued_document_file_id' => $attachment->issued_document_file_id,
            'original_filename' => $attachment->original_filename, 'sha256' => $attachment->checksum_sha256,
            'is_posted' => $entry->is_posted,
        ]);
    }
}
