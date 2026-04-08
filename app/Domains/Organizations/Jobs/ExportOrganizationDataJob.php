<?php

namespace App\Domains\Organizations\Jobs;

use App\Domains\Organizations\Mail\OrganizationExportReadyMail;
use App\Domains\Organizations\Services\OrganizationExportService;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class ExportOrganizationDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $organizationId,
        public readonly string $userId,
    ) {}

    public function handle(OrganizationExportService $exportService): void
    {
        Log::info('ExportOrganizationDataJob: starting', [
            'org_id' => $this->organizationId,
            'user_id' => $this->userId,
        ]);

        $zipPath = $exportService->generate($this->organizationId);

        $downloadUrl = URL::temporarySignedRoute(
            'settings.export.download',
            now()->addHours(48),
            ['path' => basename($zipPath)],
        );

        $user = User::findOrFail($this->userId);

        Mail::to($user->email)->locale($user->locale)->send(
            new OrganizationExportReadyMail($user, $downloadUrl),
        );

        Log::info('ExportOrganizationDataJob: complete, email sent', [
            'org_id' => $this->organizationId,
            'user_id' => $this->userId,
        ]);
    }
}
