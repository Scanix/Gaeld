<?php

namespace App\Domains\Accounting\Models;

use App\Domains\Users\Models\User;
use App\Support\Traits\BelongsToOrganization;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class JournalEntryAttachment extends Model
{
    use BelongsToOrganization, HasUuids, SoftDeletes;

    public const DOCUMENT_TYPE = 'journal_entry_attachment';

    public const UPDATED_AT = null;

    protected $fillable = [
        'organization_id', 'journal_entry_id', 'issued_document_file_id', 'user_id',
        'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256',
    ];

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'size_bytes' => 'integer'];
    }

    /** @return BelongsTo<JournalEntry, $this> */
    public function journalEntry(): BelongsTo
    {
        return $this->belongsTo(JournalEntry::class);
    }

    /** @return BelongsTo<IssuedDocumentFile, $this> */
    public function issuedDocumentFile(): BelongsTo
    {
        return $this->belongsTo(IssuedDocumentFile::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
