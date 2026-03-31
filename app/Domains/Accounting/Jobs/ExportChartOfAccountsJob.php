<?php

namespace App\Domains\Accounting\Jobs;

use App\Domains\Accounting\Mail\ChartOfAccountsExportReadyMail;
use App\Domains\Accounting\Models\Account;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

class ExportChartOfAccountsJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public readonly string $orgId,
        public readonly string $userId,
        public readonly string $format = 'csv',
    ) {}

    public function handle(): void
    {
        Log::info('ExportChartOfAccountsJob: starting', [
            'org_id' => $this->orgId,
            'user_id' => $this->userId,
            'format' => $this->format,
        ]);

        $accounts = Account::where('organization_id', $this->orgId)
            ->orderBy('code')
            ->get(['code', 'name', 'type', 'description', 'is_active']);

        $filename = 'exports/chart-of-accounts-'.$this->orgId.'-'.now()->format('Y-m-d-His');

        if ($this->format === 'json') {
            $filename .= '.json';
            Storage::disk('local')->put(
                $filename,
                $accounts->toJson(JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE),
            );
        } else {
            $filename .= '.csv';
            $handle = fopen('php://temp', 'r+');
            fputcsv($handle, ['code', 'name', 'type', 'description', 'is_active']);
            foreach ($accounts as $account) {
                fputcsv($handle, [
                    $account->code,
                    $account->name,
                    $account->type->value,
                    $account->description,
                    $account->is_active ? '1' : '0',
                ]);
            }
            rewind($handle);
            Storage::disk('local')->put($filename, stream_get_contents($handle));
            fclose($handle);
        }

        $downloadUrl = URL::temporarySignedRoute(
            'accounting.accounts.export.download',
            now()->addDay(),
            ['path' => basename($filename)],
        );

        $user = User::findOrFail($this->userId);

        Mail::to($user->email)->locale($user->locale)->send(
            new ChartOfAccountsExportReadyMail($user, $downloadUrl),
        );

        Log::info('ExportChartOfAccountsJob: complete, email sent', [
            'org_id' => $this->orgId,
            'user_id' => $this->userId,
        ]);
    }
}
