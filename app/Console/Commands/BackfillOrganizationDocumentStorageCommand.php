<?php

namespace App\Console\Commands;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Invoicing\Models\Invoice;
use App\Domains\Organizations\Models\Organization;
use App\Domains\Organizations\Models\OrganizationDocumentStorageUsage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackfillOrganizationDocumentStorageCommand extends Command
{
    protected $signature = 'organizations:backfill-document-storage {organization? : Limit the backfill to one organization}';

    protected $description = 'Calculate customer document storage usage per organization';

    public function handle(): int
    {
        $organizationId = $this->argument('organization');
        $query = Organization::query()->when($organizationId, fn ($query) => $query->whereKey($organizationId));
        $count = 0;

        $query->each(function (Organization $organization) use (&$count): void {
            $paths = Expense::query()
                ->withoutGlobalScopes()
                ->where('organization_id', $organization->id)
                ->whereNotNull('receipt_path')
                ->pluck('receipt_path')
                ->merge(
                    Invoice::query()
                        ->withoutGlobalScopes()
                        ->where('organization_id', $organization->id)
                        ->whereNotNull('justificatif_path')
                        ->pluck('justificatif_path'),
                )
                ->filter()
                ->unique()
                ->values();
            $bytes = $paths->sum(fn (string $path): int => $this->fileSize($path));
            $now = now();

            $usage = OrganizationDocumentStorageUsage::query()->firstOrNew([
                'organization_id' => $organization->id,
            ]);
            $usage->bytes_used = $bytes;
            $usage->updated_at = $now;
            $usage->created_at ??= $now;
            $usage->id ??= (string) Str::uuid();
            $usage->save();

            $count++;
        });

        $this->info("Backfilled document storage for {$count} organization(s).");

        return self::SUCCESS;
    }

    private function fileSize(string $path): int
    {
        $disk = Storage::disk('local');

        return $disk->exists($path) ? (int) $disk->size($path) : 0;
    }
}
