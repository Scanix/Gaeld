<?php

namespace App\Console\Commands;

use App\Domains\Organizations\Actions\DeleteOrganizationAction;
use App\Domains\Organizations\Models\Organization;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Deletes organizations that match a spam-signup fingerprint.
 *
 * Heuristic (ALL conditions must hold):
 *  - Organization name is pure ASCII letters, 10+ chars, no spaces/digits
 *    (bot signups generate random tokens like `lsVXrbcAjoMkQSLdDhj`).
 *  - Created within the last N days (default 7).
 *  - Zero domain activity: no invoices, expenses, contacts, bank
 *    transactions or journal entries linked to the org.
 *  - Either no subscription, or a free-plan subscription only.
 *
 * `--dry-run` lists matches without deleting. The default is dry-run for
 * safety; pass `--force` to actually delete.
 */
class CleanupSpamOrganizationsCommand extends Command
{
    protected $signature = 'gaeld:cleanup-spam-orgs
        {--days=7 : Only consider organizations created within this many days}
        {--min-length=10 : Minimum name length to flag}
        {--force : Actually delete (default is dry-run)}';

    protected $description = 'Find and delete bot-signup organizations with no activity';

    /**
     * Tables that, if they contain a row referencing the org, mean the
     * org is "active" and must be kept. Each entry maps table → FK column.
     *
     * @var array<string, string>
     */
    private const ACTIVITY_TABLES = [
        'invoices' => 'organization_id',
        'expenses' => 'organization_id',
        'contacts' => 'organization_id',
        'bank_transactions' => 'organization_id',
        'journal_entries' => 'organization_id',
    ];

    public function handle(DeleteOrganizationAction $deleteAction): int
    {
        $days = max(1, (int) $this->option('days'));
        $minLength = max(6, (int) $this->option('min-length'));
        $apply = (bool) $this->option('force');

        $candidates = Organization::query()
            ->where('created_at', '>=', now()->subDays($days))
            ->get()
            ->filter(fn ($org) => is_string($org->name)
                && preg_match('/^[A-Za-z]{'.$minLength.',}$/', $org->name) === 1);

        if ($candidates->isEmpty()) {
            $this->info('No spam-shaped organizations found.');

            return self::SUCCESS;
        }

        $toDelete = [];

        foreach ($candidates as $org) {
            if ($this->hasActivity($org->id)) {
                continue;
            }

            if ($this->hasPaidSubscription($org->id)) {
                continue;
            }

            $toDelete[] = $org;
        }

        if ($toDelete === []) {
            $this->info('No spam organizations matched the activity filter.');

            return self::SUCCESS;
        }

        $this->table(
            ['ID', 'Name', 'Created'],
            collect($toDelete)->map(fn ($o) => [$o->id, $o->name, $o->created_at?->toDateTimeString()])->all()
        );

        if (! $apply) {
            $this->warn('Dry-run: '.count($toDelete).' organization(s) would be deleted. Pass --force to apply.');

            return self::SUCCESS;
        }

        $deleted = 0;
        foreach ($toDelete as $org) {
            try {
                $deleteAction->execute($org, 'spam-cleanup');
                $deleted++;
            } catch (\Throwable $e) {
                $this->error("Failed to delete {$org->id}: {$e->getMessage()}");
            }
        }

        $this->info("Deleted {$deleted} spam organization(s).");

        return self::SUCCESS;
    }

    private function hasActivity(string $orgId): bool
    {
        foreach (self::ACTIVITY_TABLES as $table => $column) {
            if (! DB::getSchemaBuilder()->hasTable($table)) {
                continue;
            }
            if (! DB::getSchemaBuilder()->hasColumn($table, $column)) {
                continue;
            }

            if (DB::table($table)->where($column, $orgId)->exists()) {
                return true;
            }
        }

        return false;
    }

    private function hasPaidSubscription(string $orgId): bool
    {
        if (! DB::getSchemaBuilder()->hasTable('ee_subscriptions') || ! DB::getSchemaBuilder()->hasTable('ee_plans')) {
            return false;
        }

        return DB::table('ee_subscriptions as s')
            ->join('ee_plans as p', 'p.id', '=', 's.plan_id')
            ->where('s.organization_id', $orgId)
            ->where('p.price_chf', '>', 0)
            ->exists();
    }
}
