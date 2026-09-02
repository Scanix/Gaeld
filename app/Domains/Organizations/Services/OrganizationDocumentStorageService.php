<?php

namespace App\Domains\Organizations\Services;

use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationDocumentStorageUsage;
use App\Support\Contracts\OrganizationQuotaResolver;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class OrganizationDocumentStorageService
{
    public function __construct(
        private readonly OrganizationQuotaResolver $quotaResolver,
    ) {}

    public function reserve(Organization $organization, int $bytes): void
    {
        if ($bytes < 0) {
            throw new \InvalidArgumentException('File size cannot be negative.');
        }

        DB::transaction(function () use ($organization, $bytes): void {
            $this->ensureUsageRow($organization);
            $usage = OrganizationDocumentStorageUsage::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->firstOrFail();
            $limit = $this->quotaResolver->maxStorageBytes($organization);

            if ($limit !== -1 && $usage->bytes_used + $bytes > $limit) {
                throw ValidationException::withMessages([
                    'document' => [__('app.document_storage_limit_reached')],
                ]);
            }

            $usage->increment('bytes_used', $bytes);
        });
    }

    public function release(Organization $organization, int $bytes): void
    {
        if ($bytes <= 0) {
            return;
        }

        DB::transaction(function () use ($organization, $bytes): void {
            $usage = OrganizationDocumentStorageUsage::query()
                ->where('organization_id', $organization->id)
                ->lockForUpdate()
                ->first();

            if ($usage === null) {
                return;
            }

            $usage->update([
                'bytes_used' => max(0, $usage->bytes_used - $bytes),
            ]);
        });
    }

    public function bytesUsed(Organization $organization): int
    {
        return (int) OrganizationDocumentStorageUsage::query()
            ->where('organization_id', $organization->id)
            ->value('bytes_used');
    }

    public function delete(Organization $organization, ?string $path, string $disk = 'local'): void
    {
        if (! $path) {
            return;
        }

        $storage = Storage::disk($disk);
        $bytes = $storage->exists($path) ? (int) $storage->size($path) : 0;
        $storage->delete($path);

        $this->release($organization, $bytes);
    }

    private function ensureUsageRow(Organization $organization): void
    {
        $now = now();

        DB::table('organization_document_storage_usages')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'organization_id' => $organization->id,
            'bytes_used' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
    }
}
