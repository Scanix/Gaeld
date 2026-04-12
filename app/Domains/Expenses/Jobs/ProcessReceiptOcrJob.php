<?php

namespace App\Domains\Expenses\Jobs;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Notifications\OcrScanCompletedNotification;
use App\Domains\Users\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessReceiptOcrJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public readonly string $scanId,
        public readonly string $receiptPath,
        public readonly int $userId,
        public readonly string $organizationId,
    ) {}

    public function handle(ReceiptOcrInterface $ocr): void
    {
        $cacheKey = "receipt_scan:{$this->scanId}";
        $filename = basename($this->receiptPath);

        try {
            $absolutePath = Storage::disk('local')->path($this->receiptPath);

            $result = $ocr->extract($absolutePath);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'receipt_path' => $this->receiptPath,
                'extracted' => $result->toArray(),
            ], now()->addMinutes(30));

            ReceiptScan::where('scan_id', $this->scanId)
                ->where('organization_id', $this->organizationId)
                ->update(['status' => 'completed', 'extracted_data' => $result->toArray()]);

            $this->notifyUser($filename, true);
        } catch (\DomainException|\RuntimeException|\InvalidArgumentException $e) {
            Log::warning('ProcessReceiptOcrJob failed', [
                'scan_id' => $this->scanId,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'receipt_path' => $this->receiptPath,
                'extracted' => null,
            ], now()->addMinutes(30));

            ReceiptScan::where('scan_id', $this->scanId)
                ->where('organization_id', $this->organizationId)
                ->update(['status' => 'failed']);

            $this->notifyUser($filename, false);
        }
    }

    public function failed(\Throwable $e): void
    {
        $filename = basename($this->receiptPath);

        Cache::put("receipt_scan:{$this->scanId}", [
            'status' => 'failed',
            'receipt_path' => $this->receiptPath,
            'extracted' => null,
        ], now()->addMinutes(30));

        ReceiptScan::where('scan_id', $this->scanId)
            ->where('organization_id', $this->organizationId)
            ->update(['status' => 'failed']);

        $this->notifyUser($filename, false);
    }

    private function notifyUser(string $filename, bool $success): void
    {
        $user = User::find($this->userId);

        $user?->notify(new OcrScanCompletedNotification($filename, $success, $this->scanId));
    }
}
