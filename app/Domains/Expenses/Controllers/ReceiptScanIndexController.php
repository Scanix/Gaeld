<?php

namespace App\Domains\Expenses\Controllers;

use App\Domains\Expenses\Models\Expense;
use App\Domains\Expenses\Models\ReceiptScan;
use App\Domains\Organizations\Services\CurrentOrganization;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Lists pending/completed OCR receipt scans that have not yet been turned
 * into expenses, and lets users discard scans they no longer need.
 *
 * Scans are ephemeral (48 h TTL via `expires_at`) and become `validated`
 * only after `ExpenseController::store` consumes the `scan_id`.
 */
class ReceiptScanIndexController extends Controller
{
    public function index(CurrentOrganization $currentOrg): Response
    {
        $this->authorize('create', Expense::class);

        $scans = ReceiptScan::query()
            ->where('organization_id', $currentOrg->id())
            ->whereIn('status', ['pending', 'completed'])
            ->where('expires_at', '>', now())
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (ReceiptScan $scan) => [
                'id' => $scan->id,
                'scan_id' => $scan->scan_id,
                'status' => $scan->status->value,
                'expires_at' => $scan->expires_at->toIso8601String(),
                'created_at' => $scan->created_at?->toIso8601String(),
                'extracted' => $scan->extracted_data,
                'create_url' => '/expenses/create?scan_id='.$scan->scan_id,
            ])
            ->all();

        return Inertia::render('Expenses/ReceiptScans/Index', [
            'scans' => $scans,
        ]);
    }

    public function discard(CurrentOrganization $currentOrg, string $scanId): RedirectResponse
    {
        $this->authorize('create', Expense::class);

        $scan = ReceiptScan::query()
            ->where('organization_id', $currentOrg->id())
            ->where('scan_id', $scanId)
            ->firstOrFail();

        // Expire the scan now so it disappears from the index without
        // hard-deleting (keeps the audit trail intact).
        $scan->forceFill(['expires_at' => now()->subSecond()])->save();

        return redirect()->route('expenses.receipt-scans.index')
            ->with('success', __('app.receipt_scan_discarded'));
    }
}
