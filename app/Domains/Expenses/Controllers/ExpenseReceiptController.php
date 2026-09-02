<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Expenses\Requests\ScanReceiptRequest;
use App\Domains\Organizations\Enums\Permission;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Domains\Organizations\Services\OrganizationDocumentStorageService;
use App\Http\Controllers\Controller;
use App\Support\Contracts\OrganizationQuotaResolver;
use App\Support\Services\FileUploadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Receipt upload, OCR scanning, and file management for expenses.
 */
class ExpenseReceiptController extends Controller
{
    public function __construct(
        private FileUploadService $uploadService,
        private OrganizationQuotaResolver $quotaResolver,
        private OrganizationDocumentStorageService $documentStorage,
    ) {}

    public function removeReceipt(Expense $expense): RedirectResponse
    {
        $this->authorize('update', $expense);

        if ($expense->receipt_path) {
            $this->documentStorage->delete($expense->organization, $expense->receipt_path);
            $expense->update(['receipt_path' => null]);
        }

        return redirect()->route('expenses.show', $expense)
            ->with('success', __('app.receipt_removed'));
    }

    public function downloadReceipt(Expense $expense, Request $request): StreamedResponse
    {
        $this->authorize('view', $expense);

        if (! $expense->receipt_path || ! Storage::disk('local')->exists($expense->receipt_path)) {
            abort(404);
        }

        $filename = basename($expense->receipt_path);

        if ($request->boolean('inline')) {
            return Storage::disk('local')->response(
                $expense->receipt_path,
                $filename,
            );
        }

        return Storage::disk('local')->download(
            $expense->receipt_path,
            $filename,
        );
    }

    public function scanReceipt(ScanReceiptRequest $request, CurrentOrganization $currentOrg): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $orgId = $currentOrg->id();
        $dailyKey = "ocr_daily:{$orgId}:".now()->toDateString();
        $monthlyKey = 'ocr_monthly:'.$orgId.':'.now()->format('Y-m');

        $organization = $currentOrg->get();
        $dailyLimit = $this->quotaResolver->maxOcrScansPerDay($organization);
        $monthlyLimit = $this->quotaResolver->maxOcrScansPerMonth($organization);

        if ($dailyLimit !== -1 && ! $this->reserveCounter($dailyKey, $dailyLimit, now()->startOfDay()->addDay())) {
            return response()->json([
                'message' => __('app.ocr_daily_limit_reached', ['limit' => $dailyLimit]),
            ], 429);
        }

        if ($monthlyLimit !== -1 && ! $this->reserveCounter($monthlyKey, $monthlyLimit, now()->startOfMonth()->addMonth())) {
            if ($dailyLimit !== -1) {
                Cache::decrement($dailyKey);
            }

            return response()->json([
                'message' => __('app.ocr_monthly_limit_reached', ['limit' => $monthlyLimit]),
            ], 429);
        }

        $receipt = $request->file('receipt');
        $receiptBytes = (int) $receipt->getSize();
        $this->documentStorage->reserve($organization, $receiptBytes);

        try {
            $receiptPath = $this->uploadService->store($receipt, "receipts/{$orgId}");
        } catch (Throwable $exception) {
            $this->documentStorage->release($organization, $receiptBytes);

            throw $exception;
        }
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

        return response()->json([
            'scan_id' => $scanId,
            'receipt_path' => $receiptPath,
        ]);
    }

    private function reserveCounter(string $key, int $limit, \DateTimeInterface $expiresAt): bool
    {
        Cache::add($key, 0, $expiresAt);
        $newCount = Cache::increment($key);

        if ($newCount > $limit) {
            Cache::decrement($key);

            return false;
        }

        return true;
    }

    public function scanReceiptStatus(Request $request, CurrentOrganization $currentOrg, string $scanId): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $data = Cache::get("receipt_scan:{$scanId}");

        if ($data) {
            // Verify org ownership even on the cache-hit path so an authenticated user
            // from a different organization cannot read results by knowing the scan UUID.
            $ownedByScan = ReceiptScan::where('scan_id', $scanId)
                ->where('organization_id', $currentOrg->id())
                ->when(
                    $this->isSelfService(),
                    fn ($query) => $query->where('user_id', $request->user()->id),
                )
                ->where('expires_at', '>', now())
                ->exists();

            if (! $ownedByScan) {
                return response()->json(['status' => 'not_found'], 404);
            }
        } else {
            // Cache expired (30 min TTL) — fall back to DB record (48 h TTL)
            $scan = ReceiptScan::where('scan_id', $scanId)
                ->where('organization_id', $currentOrg->id())
                ->when(
                    $this->isSelfService(),
                    fn ($query) => $query->where('user_id', $request->user()->id),
                )
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

    private function isSelfService(): bool
    {
        return request()->user()->hasPermissionTo(Permission::ExpensesViewOwn)
            && ! request()->user()->hasPermissionTo(Permission::ExpensesView);
    }
}
