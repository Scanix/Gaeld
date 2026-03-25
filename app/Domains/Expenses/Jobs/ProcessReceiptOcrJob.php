<?php

namespace App\Domains\Expenses\Jobs;

use App\Domains\Expenses\Contracts\ReceiptOcrInterface;
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
    ) {}

    public function handle(ReceiptOcrInterface $ocr): void
    {
        $cacheKey = "receipt_scan:{$this->scanId}";

        try {
            $absolutePath = Storage::disk('local')->path($this->receiptPath);

            $result = $ocr->extract($absolutePath);

            Cache::put($cacheKey, [
                'status' => 'completed',
                'receipt_path' => $this->receiptPath,
                'extracted' => $result->toArray(),
            ], now()->addMinutes(30));
        } catch (\Throwable $e) {
            Log::warning('ProcessReceiptOcrJob failed', [
                'scan_id' => $this->scanId,
                'error' => $e->getMessage(),
            ]);

            Cache::put($cacheKey, [
                'status' => 'failed',
                'receipt_path' => $this->receiptPath,
                'extracted' => null,
            ], now()->addMinutes(30));
        }
    }
}
