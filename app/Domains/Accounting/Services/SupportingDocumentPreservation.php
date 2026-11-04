<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/** Retain uploaded source bytes even when a live attachment is later replaced. */
class SupportingDocumentPreservation
{
    public function preserve(Expense|Invoice $document, string $field, string $type, bool $original = false): void
    {
        $path = $original ? $document->getRawOriginal($field) : $document->getAttribute($field);
        if (! $path) {
            return;
        }
        $prefix = ($field === 'receipt_path' ? 'receipts/' : 'justificatifs/').$document->organization_id.'/';
        if (! str_starts_with($path, $prefix) || str_contains($path, '..') || str_contains($path, '\\')) {
            throw new \RuntimeException('Supporting document is outside the organization.');
        }
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            if ($original) {
                throw new \RuntimeException('Restore the missing original supporting document before replacing it.');
            }

            return;
        }
        DB::transaction(function () use ($document, $field, $type, $path, $disk): void {
            Organization::withoutGlobalScopes()->whereKey($document->organization_id)->lockForUpdate()->firstOrFail();
            $bytes = $disk->get($path);
            $hash = hash('sha256', $bytes);
            $versions = IssuedDocumentFile::withoutGlobalScopes()->where('organization_id', $document->organization_id)->where('document_type', $type)->where('document_id', $document->getKey());
            $existing = (clone $versions)->where('checksum_sha256', $hash)->first();
            if ($existing !== null) {
                if (! $disk->exists($existing->storage_path) || ! hash_equals($hash, hash('sha256', $disk->get($existing->storage_path)))) {
                    throw new \RuntimeException('Preserved supporting evidence is missing or changed.');
                }

                return;
            }
            $id = (string) Str::uuid();
            $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
            $extension = preg_match('/^[a-z0-9]{1,10}$/D', $extension) ? $extension : 'bin';
            $target = 'archives/issued/'.$document->organization_id.'/'.$type.'/'.$document->getKey().'/'.$id.'.'.$extension;
            if (! $disk->put($target, $bytes)) {
                throw new \RuntimeException('Cannot preserve the original supporting document.');
            }
            $dateField = $field === 'receipt_path' ? 'date' : 'issue_date';
            $date = Carbon::parse($document->getRawOriginal($dateField) ?? $document->getAttribute($dateField))->toDateString();
            IssuedDocumentFile::create([
                'id' => $id, 'organization_id' => $document->organization_id,
                'fiscal_year_id' => FiscalYear::withoutGlobalScopes()->where('organization_id', $document->organization_id)->forDate($date)->value('id'),
                'document_type' => $type, 'document_id' => $document->getKey(), 'version' => ((int) (clone $versions)->max('version')) + 1,
                'storage_path' => $target, 'checksum_sha256' => $hash, 'created_at' => now(),
            ]);
        });
    }
}
