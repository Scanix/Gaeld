<?php

namespace App\Domains\Reporting\Jobs;

use App\Domains\Reporting\Mail\AccountingExportReadyMail;
use App\Domains\Reporting\Services\AccountingExportService;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class GenerateAccountingExportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $orgId,
        public readonly string $fiscalYear,
        public readonly string $userId,
    ) {}

    public function handle(AccountingExportService $exportService): void
    {
        Log::info('GenerateAccountingExportJob: starting', [
            'org_id' => $this->orgId,
            'fiscal_year' => $this->fiscalYear,
            'user_id' => $this->userId,
        ]);

        $zipPath = $exportService->generateExport($this->orgId, $this->fiscalYear);

        $relativePath = basename($zipPath);

        $downloadUrl = URL::temporarySignedRoute(
            'accounting.export.download',
            now()->addDay(),
            ['path' => $relativePath],
        );

        $user = User::findOrFail($this->userId);

        Mail::to($user->email)->locale($user->locale)->send(
            new AccountingExportReadyMail($user, $this->fiscalYear, $downloadUrl),
        );

        Log::info('GenerateAccountingExportJob: complete, email sent', [
            'org_id' => $this->orgId,
            'fiscal_year' => $this->fiscalYear,
            'user_id' => $this->userId,
        ]);
    }
}
