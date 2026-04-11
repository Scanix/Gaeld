<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Requests\ScanReceiptRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use App\Support\FeatureFlag;
use App\Support\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Receipt upload, OCR scanning, and file management for expenses.
 */
class ExpenseReceiptController extends Controller
{
    public function __construct(
        private FileUploadService $uploadService,
    ) {}

    public function removeReceipt(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if ($expense->receipt_path) {
            $this->uploadService->delete($expense->receipt_path);
            $expense->update(['receipt_path' => null]);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.receipt_removed'));
    }

    public function downloadReceipt(Expense $expense): StreamedResponse
    {
        $this->authorize('view', $expense);

        if (! $expense->receipt_path || ! Storage::disk('local')->exists($expense->receipt_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $expense->receipt_path,
            basename($expense->receipt_path),
        );
    }

    public function scanReceipt(ScanReceiptRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $orgId = $currentOrg->id();
        $dailyKey = "ocr_daily:{$orgId}:".now()->toDateString();

        $limit = $this->resolveOcrDailyLimit($currentOrg);

        if ($limit !== -1 && (int) Cache::get($dailyKey, 0) >= $limit) {
            return response()->json(['message' => __('app.ocr_daily_limit_reached')], 429);
        }

        $receiptPath = $this->uploadService->store($request->file('receipt'), "receipts/{$orgId}");
        $scanId = Str::uuid()->toString();

        ReceiptScan::create([
            'organization_id' => $orgId,
            'user_id' => $request->user()->id,
            'scan_id' => $scanId,
            'receipt_path' => $receiptPath,
            'status' => 'pending',
            'expires_at' => now()->addHours(48),
        ]);

        Cache::put("receipt_scan:{$scanId}", [
            'status' => 'processing',
            'receipt_path' => $receiptPath,
            'extracted' => null,
        ], now()->addMinutes(30));

        ProcessReceiptOcrJob::dispatch($scanId, $receiptPath, $request->user()->id, $orgId);

        Cache::add($dailyKey, 0, now()->startOfDay()->addDay());
        Cache::increment($dailyKey);

        return response()->json([
            'scan_id' => $scanId,
            'receipt_path' => $receiptPath,
        ]);
    }

    private function resolveOcrDailyLimit(CurrentOrganization $currentOrg): int
    {
        if (FeatureFlag::isSaas()) {
            $org = $currentOrg->get();
            $plan = $org->activeSubscription?->plan;
            if ($plan && isset($plan->max_ocr_scans_per_day)) {
                return (int) $plan->max_ocr_scans_per_day;
            }
        }

        return (int) config('services.ocr.daily_limit', 3);
    }

    public function scanReceiptStatus(Request $request, CurrentOrganization $currentOrg, string $scanId): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $data = Cache::get("receipt_scan:{$scanId}");

        if (! $data) {
            // Cache expired (30 min TTL) — fall back to DB record (48 h TTL)
            $scan = ReceiptScan::where('scan_id', $scanId)
                ->where('organization_id', $currentOrg->id())
                ->where('expires_at', '>', now())
                ->first();

            if (! $scan) {
                return response()->json(['status' => 'not_found'], 404);
            }

            $data = [
                'status' => $scan->status->value,
                'receipt_path' => $scan->receipt_path,
                'extracted' => $scan->extracted_data,
            ];
        }

        return response()->json($data);
    }
}
