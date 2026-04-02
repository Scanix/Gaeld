<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Jobs\ProcessReceiptOcrJob;
use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Requests\ScanReceiptRequest;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
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

        $receiptPath = $this->uploadService->store($request->file('receipt'), "receipts/{$currentOrg->id()}");
        $scanId = Str::uuid()->toString();

        Cache::put("receipt_scan:{$scanId}", [
            'status' => 'processing',
            'receipt_path' => $receiptPath,
            'extracted' => null,
        ], now()->addMinutes(30));

        ProcessReceiptOcrJob::dispatch($scanId, $receiptPath);

        return response()->json([
            'scan_id' => $scanId,
            'receipt_path' => $receiptPath,
        ]);
    }

    public function scanReceiptStatus(Request $request, string $scanId): JsonResponse
    {
        $this->authorize('create', Expense::class);

        $data = Cache::get("receipt_scan:{$scanId}");

        if (! $data) {
            return response()->json(['status' => 'not_found'], 404);
        }

        return response()->json($data);
    }
}
