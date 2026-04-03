<?php

namespace App\Domains\Invoicing\Queries;

use App\Domains\Invoicing\Enums\InvoiceStatus;
use App\Domains\Invoicing\Models\Invoice;
use App\Support\DTOs\SummaryResult;
use Illuminate\Support\Collection;

/**
 * Read-only reporting queries for invoices: revenue summaries,
 * unpaid counts, and QR reference lookups.
 */
class InvoiceReportingQuery
{
    public function yearlyRevenue(string $orgId, int $year): string
    {
        $total = Invoice::where('organization_id', $orgId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->sum('total');

        return $total ? (string) $total : '0.00';
    }

    public function unpaidSummary(string $orgId): SummaryResult
    {
        $row = Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(total), 0) as total')
            ->first();

        return new SummaryResult(
            count: (int) ($row->count ?? 0),
            total: (string) ($row->total ?? '0'),
        );
    }

    public function paidInYear(string $orgId, int $year): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->where('status', InvoiceStatus::Paid)
            ->whereYear('issue_date', $year)
            ->select('number', 'total', 'issue_date')
            ->get();
    }

    public function sentOrOverdueDueInYear(string $orgId, int $year): Collection
    {
        return Invoice::where('organization_id', $orgId)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->whereYear('due_date', $year)
            ->select('number', 'total', 'due_date')
            ->get();
    }

    public function hasMatchingQrReference(string $organizationId, string $reference): bool
    {
        return Invoice::where('organization_id', $organizationId)
            ->where('qr_reference', $reference)
            ->whereIn('status', [InvoiceStatus::Sent, InvoiceStatus::Overdue])
            ->exists();
    }
}
