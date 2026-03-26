<?php

namespace App\Console\Commands;

use App\Domains\Banking\Models\BankAccount;
use App\Domains\Banking\Services\ReconciliationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\PermissionRegistrar;

class AutoReconcileCommand extends Command
{
    protected $signature = 'gaeld:auto-reconcile';

    protected $description = 'Auto-reconcile unreconciled bank transactions for all active bank accounts (EE only)';

    public function handle(ReconciliationService $reconciliationService): int
    {
        $bankAccounts = BankAccount::where('is_active', true)
            ->whereHas('transactions', fn ($q) => $q->where('is_reconciled', false))
            ->with('organization')
            ->get();

        if ($bankAccounts->isEmpty()) {
            $this->info('No bank accounts with unreconciled transactions.');

            return self::SUCCESS;
        }

        $totalMatched = 0;
        $totalUnmatched = 0;

        foreach ($bankAccounts as $bankAccount) {
            // Set Spatie team context so permission checks work.
            app(PermissionRegistrar::class)->setPermissionsTeamId($bankAccount->organization_id);

            try {
                $result = $reconciliationService->autoReconcile($bankAccount);
                $totalMatched += $result['matched'];
                $totalUnmatched += $result['unmatched'];

                $this->line("  {$bankAccount->name}: {$result['matched']} matched, {$result['unmatched']} unmatched");
            } catch (\Throwable $e) {
                Log::warning('Auto-reconcile failed for bank account', [
                    'bank_account_id' => $bankAccount->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  {$bankAccount->name}: skipped — {$e->getMessage()}");
            }
        }

        $this->info("Auto-reconciliation complete: {$totalMatched} matched, {$totalUnmatched} unmatched.");

        return self::SUCCESS;
    }
}
