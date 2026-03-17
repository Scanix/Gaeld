<?php

namespace App\Jobs;

use App\Domains\Banking\Models\BankImport;
use App\Domains\Banking\Services\RuleEngineService;
use App\Services\FeatureFlag;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

/**
 * Process a CAMT bank import through the Rule Engine (EE).
 *
 * Dispatched after BankImportService finishes parsing a CAMT file.
 * Idempotent: skips already-reconciled transactions.
 */
class ProcessBankImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly BankImport $bankImport,
    ) {}

    public function handle(RuleEngineService $ruleEngine): void
    {
        if (FeatureFlag::disabled('rule_engine')) {
            return;
        }

        $orgId = $this->bankImport->bankAccount->organization_id;

        Log::info('ProcessBankImportJob: running rule engine', [
            'bank_import_id' => $this->bankImport->id,
            'organization_id' => $orgId,
        ]);

        $stats = $ruleEngine->runForOrganization($orgId);

        Log::info('ProcessBankImportJob: complete', array_merge(
            ['bank_import_id' => $this->bankImport->id],
            $stats,
        ));
    }
}
