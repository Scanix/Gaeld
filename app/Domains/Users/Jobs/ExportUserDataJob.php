<?php

namespace App\Domains\Users\Jobs;

use App\Domains\Users\Mail\DataExportReady;
use App\Domains\Users\Models\User;
use App\Domains\Users\Services\DataExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ExportUserDataJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly User $user,
    ) {}

    public function handle(DataExportService $exportService): void
    {
        Log::info('ExportUserDataJob: starting export', ['user_id' => $this->user->id]);

        $data = $exportService->export($this->user);

        $filename = 'exports/user-'.$this->user->id.'-'.now()->format('Y-m-d-His').'.json';

        Storage::disk('local')->put($filename, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

        $downloadUrl = URL::temporarySignedRoute(
            'profile.export.download',
            now()->addHours(48),
            ['filename' => basename($filename)],
        );

        Mail::to($this->user->email)->locale($this->user->locale)->send(
            new DataExportReady($this->user, $downloadUrl),
        );

        Log::info('ExportUserDataJob: export complete, email sent', ['user_id' => $this->user->id]);
    }
}
