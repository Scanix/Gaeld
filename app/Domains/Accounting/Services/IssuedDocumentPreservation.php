<?php

namespace App\Domains\Accounting\Services;

use App\Domains\Accounting\Models\FiscalYear;
use App\Domains\Accounting\Models\IssuedDocumentFile;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Payroll\Models\SalarySlip;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class IssuedDocumentPreservation
{
    public function store(Invoice|SalarySlip $document, string $type, string $bytes, string $documentDate): IssuedDocumentFile
    {
        if (! in_array($type, ['invoice_pdf', 'salary_slip_pdf'], true) || ! str_starts_with($bytes, '%PDF-')) {
            throw new \InvalidArgumentException('An issued PDF document is required.');
        }

        return DB::transaction(function () use ($document, $type, $bytes, $documentDate): IssuedDocumentFile {
            $document->newQueryWithoutScopes()->whereKey($document->getKey())->lockForUpdate()->firstOrFail();
            $query = IssuedDocumentFile::withoutGlobalScopes()->where('organization_id', $document->organization_id)
                ->where('document_type', $type)->where('document_id', $document->getKey());
            $hash = hash('sha256', $bytes);
            $existing = (clone $query)->where('checksum_sha256', $hash)->first();
            if ($existing) {
                if (! Storage::disk('local')->exists($existing->storage_path)
                    || ! hash_equals($hash, hash('sha256', Storage::disk('local')->get($existing->storage_path)))) {
                    throw new \RuntimeException('Previously issued evidence is missing or altered; restoration is required.');
                }

                return $existing;
            }
            $id = (string) Str::uuid();
            $path = 'archives/issued/'.$document->organization_id.'/'.$type.'/'.$document->getKey().'/'.$id.'.pdf';
            if (! Storage::disk('local')->put($path, $bytes)
                || ! hash_equals($hash, hash('sha256', Storage::disk('local')->get($path)))) {
                throw new \RuntimeException('Unable to preserve the issued PDF.');
            }
            $record = IssuedDocumentFile::create([
                'id' => $id, 'organization_id' => $document->organization_id,
                'fiscal_year_id' => FiscalYear::withoutGlobalScopes()->where('organization_id', $document->organization_id)
                    ->forDate($documentDate)->value('id'),
                'document_type' => $type, 'document_id' => $document->getKey(),
                'version' => ((int) (clone $query)->max('version')) + 1,
                'storage_path' => $path, 'checksum_sha256' => $hash, 'created_at' => now(),
            ]);
            app(ArchiveAccessLog::class)->record($document->organization_id, null, 'document.version_preserved', auth()->user(), [
                'evidence_id' => $id, 'document_type' => $type, 'document_id' => $document->getKey(), 'sha256' => $hash,
            ]);

            return $record;
        });
    }
}
